<?php
$type = isset( $_REQUEST[ "type" ] ) ? ( int )$_REQUEST[ "type" ] : -1;

$limit = isset( $_REQUEST[ "limit" ] ) ? ( int )$_REQUEST[ "limit" ] : 0;
$offset = isset( $_REQUEST[ "offset" ] ) ? ( int )$_REQUEST[ "offset" ] : 0;

$search_query = isset( $_REQUEST[ "query" ] ) ? $_REQUEST[ "query" ] : "";

$search_id = 5;

// type: 0 = groups, 1 = channels
$json_array = [ "data" => array( "type" => -1, "total" => 0, "id" => -1, "name" => "", "values" => [] ) ];

$database_path = "databases/";

$channels_file_path = $database_path . "channels.db";
$vod_file_path = $database_path . "vod.db";

function GetRange( $_total, $_limit, $_offset )
{
	$ret = [ 0, 0, 0, 0 ];

	if ( $_limit >= 0 && $_limit <= $_total )
	{
		if ( abs( $_offset ) >= $_total )
		{
			if ( $_offset < 0 )
			{
				$_offset = ( $_total + $_offset ) % $_total;
			}
			else
			{
				$_offset = $_offset % $_total;
			}
		}

		$old_limit = $_limit;
		$old_offset = $_offset;

		if ( ( $old_offset + $old_limit ) > $_total )
		{
			$old_offset = $old_offset - $_total;
		}

		if ( $old_offset < 0 )
		{
			$_limit = $_limit + $old_offset;
			$_offset = 0;
		}

		if ( $_limit > 0 )
		{
			$ret[ 0 ] = $_limit;
			$ret[ 1 ] = $_offset;
		}

		if ( $old_offset < 0 )
		{
			$_limit = abs( $old_offset );
			if ( $_limit > $old_limit )
			{
				$_limit = $old_limit;
			}
			$_offset = ( $_total + $old_offset ) % $_total;

			if ( $_limit > 0 )
			{
				$ret[ 2 ] = $_limit;
				$ret[ 3 ] = $_offset;
			}
		}
	}
	else
	{
		$ret[ 0 ] = $_total;
	}

	return $ret;
}

function MakeQuery( $_range, $_query )
{
	$query = "SELECT 0 WHERE 0";

	if ( $_range[ 0 ] != 0 && $_range[ 2 ] != 0 )
	{
		$query = "SELECT * FROM (" . $_query . " LIMIT " . $_range[ 0 ] . " OFFSET " . $_range[ 1 ] . ")";
		$query .= " UNION ALL";
		$query .= " SELECT * FROM (" . $_query . " LIMIT " . $_range[ 2 ] . " OFFSET " . $_range[ 3 ] . ")";
	}
	else if ( $_range[ 0 ] != 0 )
	{
		$query = $_query . " LIMIT " . $_range[ 0 ] . " OFFSET " . $_range[ 1 ];
	}
	else if ( $_range[ 2 ] != 0 )
	{
		$query = $_query . " LIMIT " . $_range[ 2 ] . " OFFSET " . $_range[ 3 ];
	}

	return $query;
}

if ( $type == 0 )	// Live TV
{
	$db = new SQLite3( $channels_file_path );

	$tables_exist = 0;

	// Do our tables exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table'";
	$results = $db->query( $query );
	while ( $row = $results->fetchArray() )
	{
		if ( $row[ "name" ] == "group" || $row[ "name" ] == "channel" || $row[ "name" ] == "group_channel_map" )
		{
			++$tables_exist;
		}
	}

	if ( $tables_exist == 3 )
	{
		$json_array[ "data" ][ "id" ] = $search_id;
        $json_array[ "data" ][ "name" ] = "Search";
        $json_array[ "data" ][ "type" ] = 1;

		$t_query = " FROM [channel] WHERE id IN (" .
        			"SELECT id FROM [channel] WHERE name LIKE '" . $search_query . " %'".
											   " OR name LIKE '% " . $search_query . "'" .
											   " OR name LIKE '% " . $search_query . " %'" .
											   " OR LOWER(name) = '" . strtolower( $search_query ) . "'" .
											   " OR alias LIKE '" . $search_query . " %'".
											   " OR alias LIKE '% " . $search_query . "'" .
											   " OR alias LIKE '% " . $search_query . " %'" .
											   " OR LOWER(alias) = '" . strtolower( $search_query ) . "'" .
					" UNION" .
					" SELECT id FROM [epg] WHERE description LIKE '" . $search_query . " %'".
											" OR description LIKE '% " . $search_query . "'" .
											" OR description LIKE '% " . $search_query . " %'" .
											" OR LOWER(description) = '" . strtolower( $search_query ) . "'" .
					") ORDER BY number ASC";

        $query = "SELECT COUNT(*)" . $t_query;
        $results = $db->query( $query );
        if ( $row = $results->fetchArray() )
        {
            $total = $row[ "COUNT(*)" ];

            $json_array[ "data" ][ "total" ] = $total;

            $range = GetRange( $total, $limit, $offset );
            $query = MakeQuery( $range, "SELECT *" . $t_query );
            $results = $db->query( $query );

            while ( $row = $results->fetchArray() )
            {
                $name = ( $row[ "alias" ] != NULL ? $row[ "alias" ] : $row[ "name" ] );
                $headers = ( $row[ "headers" ] != NULL ? $row[ "headers" ] : "" );

                array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
                                                                      "number" => $row[ "number" ],
                                                                      "name" => $name,
                                                                      "guide_id" => $row[ "guide_id" ],
                                                                      "url" => $row[ "url" ],
                                                                      "logo_url" => $row[ "logo_url" ],
                                                                      "headers" => $headers,
                                                                      "favorite" => $row[ "favorite" ] ) );
            }
        }
    }

    $db->close();
}
else if ( $type == 1 )	// Movies
{
	$db = new SQLite3( $vod_file_path );

	$tables_exist = 0;

	// Do our tables exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table'";
	$results = $db->query( $query );
	while ( $row = $results->fetchArray() )
	{
		if ( $row[ "name" ] == "movie_group" || $row[ "name" ] == "movie" || $row[ "name" ] == "group_movie_map" )
		{
			++$tables_exist;
		}
	}

	if ( $tables_exist == 3 )
	{
		$json_array[ "data" ][ "id" ] = $search_id;
        $json_array[ "data" ][ "name" ] = "Search";
        $json_array[ "data" ][ "type" ] = 1;

		$t_query = " FROM [movie] WHERE name LIKE '" . $search_query . " %'".
								   " OR name LIKE '% " . $search_query . "'" .
								   " OR name LIKE '% " . $search_query . " %'" .
								   " OR LOWER(name) = '" . strtolower( $search_query ) . "'" .
								   " OR description LIKE '" . $search_query . " %'".
								   " OR description LIKE '% " . $search_query . "'" .
								   " OR description LIKE '% " . $search_query . " %'" .
								   " OR LOWER(description) = '" . strtolower( $search_query ) . "'" .
								   " ORDER BY name ASC";

		$query = "SELECT COUNT(*)" . $t_query;
		$results = $db->query( $query );
		if ( $row = $results->fetchArray() )
		{
			$total = $row[ "COUNT(*)" ];

			$json_array[ "data" ][ "total" ] = $total;

			$range = GetRange( $total, $limit, $offset );
			$query = MakeQuery( $range, "SELECT *" . $t_query );
			$results = $db->query( $query );
	
			while ( $row = $results->fetchArray() )
			{
				$subtitle_url = ( $row[ "subtitle_url" ] != NULL ? $row[ "subtitle_url" ] : "" );
				$headers = ( $row[ "headers" ] != NULL ? $row[ "headers" ] : "" );

				array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																	  "name" => $row[ "name" ],
																	  "url" => $row[ "url" ],
																	  "extension" => $row[ "extension" ],
																	  "year" => $row[ "year" ],
																	  "subtitle_url" => $subtitle_url,
																	  "logo_url" => $row[ "logo_url" ],
																	  "headers" => $headers ) );
			}
		}
	}

	$db->close();
}
else if ( $type == 2 )	// TV Shows
{
	$db = new SQLite3( $vod_file_path );

	$tables_exist = 0;
	
	// Does our tables exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table'";
	$results = $db->query( $query );
	while ( $row = $results->fetchArray() )
	{
		if ( $row[ "name" ] == "series_group" || $row[ "name" ] == "series" || $row[ "name" ] == "group_series_map" )
		{
			++$tables_exist;
		}
	}
	
	if ( $tables_exist == 3 )
	{
		$json_array[ "data" ][ "id" ] = $search_id;
        $json_array[ "data" ][ "name" ] = "Search";
		$json_array[ "data" ][ "type" ] = 0;

		$t_query = " FROM [series_group] WHERE type=1 AND (" .
						   "name LIKE '" . $search_query . " %'".
					   " OR name LIKE '% " . $search_query . "'" .
					   " OR name LIKE '% " . $search_query . " %'" .
					   " OR LOWER(name) = '" . strtolower( $search_query ) . "'" .
					") ORDER BY name ASC";

		$query = "SELECT COUNT(*)" . $t_query;
		$results = $db->query( $query );
		if ( $row = $results->fetchArray() )
		{
			$total = $row[ "COUNT(*)" ];

			$json_array[ "data" ][ "total" ] = $total;

			$range = GetRange( $total, $limit, $offset );
			$query = MakeQuery( $range, "SELECT id, name, type, year, season" . $t_query );
			$results = $db->query( $query );

			while ( $row = $results->fetchArray() )
			{
				array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																	  "name" => $row[ "name" ],
																	  "type" => $row[ "type" ],
																	  "year" => $row[ "year" ],
																	  "season" => $row[ "season" ] ) );
			}
		}
	}

	$db->close();
}

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
