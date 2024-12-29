<?php
$type = isset( $_REQUEST[ "type" ] ) ? ( int )$_REQUEST[ "type" ] : -1;
$sort = isset( $_REQUEST[ "sort" ] ) ? ( int )$_REQUEST[ "sort" ] : -1;
$id = isset( $_REQUEST[ "id" ] ) ? ( int )$_REQUEST[ "id" ] : -1;

$limit = isset( $_REQUEST[ "limit" ] ) ? ( int )$_REQUEST[ "limit" ] : 0;
$offset = isset( $_REQUEST[ "offset" ] ) ? ( int )$_REQUEST[ "offset" ] : 0;

$channel_number = isset( $_REQUEST[ "channel_number" ] ) ? ( int )$_REQUEST[ "channel_number" ] : -1;
$search_query = isset( $_REQUEST[ "query" ] ) ? $_REQUEST[ "query" ] : "";

$get_guide_name = isset( $_REQUEST[ "get_guide_name" ] ) ? filter_var( $_REQUEST[ "get_guide_name" ], FILTER_VALIDATE_BOOLEAN ) : false;

$sort_type = "number";
if ( $sort == 1 )
{
	$sort_type = "name";
}

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
		if ( $row[ "name" ] == "group" || $row[ "name" ] == "channel" || $row[ "name" ] == "group_channel_map" || $row[ "name" ] == "guide_id" )
		{
			++$tables_exist;
		}
	}

	if ( $tables_exist == 4 )
	{
		$json_array[ "data" ][ "id" ] = $id;
		if ( $id == 0 )
		{
			$json_array[ "data" ][ "name" ] = "Live TV";
		}
		else
		{
			$results = $db->query( "SELECT name FROM [group] WHERE id=" . $id );
			if ( $row = $results->fetchArray() )
			{
				$json_array[ "data" ][ "name" ] = $row[ "name" ];
			}
		}

		if ( $id == 1 )	// Get All Channels.
		{
			$json_array[ "data" ][ "type" ] = 1;

			$t_query = " FROM [channel] ORDER BY " . $sort_type . " ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$guide_ids = [];
				if ( $get_guide_name )
				{
					$query = "SELECT * FROM [guide_id] ORDER BY id ASC";
					$results = $db->query( $query );

					while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
					{
						$guide_ids += [ $arr[ "id" ] => $arr[ "name" ] ];
					}
				}

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
																		  "guide_name" => ( array_key_exists( $row[ "guide_id" ], $guide_ids ) ? $guide_ids[ $row[ "guide_id" ] ] : "" ),
																		  "url" => $row[ "url" ],
																		  "extension" => $row[ "extension" ],
																		  "logo_url" => $row[ "logo_url" ],
																		  "headers" => $headers,
																		  "favorite" => $row[ "favorite" ] ) );
				}
			}
		}
		else if ( $id == 4 )	// Get Favorite Channels.
		{
			$json_array[ "data" ][ "type" ] = 1;

			$t_query = " FROM [channel] WHERE favorite = 1 ORDER BY " . $sort_type . " ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$guide_ids = [];
				if ( $get_guide_name )
				{
					$query = "SELECT * FROM [guide_id] ORDER BY id ASC";
					$results = $db->query( $query );

					while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
					{
						$guide_ids += [ $arr[ "id" ] => $arr[ "name" ] ];
					}
				}

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
																		  "guide_name" => ( array_key_exists( $row[ "guide_id" ], $guide_ids ) ? $guide_ids[ $row[ "guide_id" ] ] : "" ),
																		  "url" => $row[ "url" ],
																		  "extension" => $row[ "extension" ],
																		  "logo_url" => $row[ "logo_url" ],
																		  "headers" => $headers,
																		  "favorite" => $row[ "favorite" ]  ) );
				}
			}
		}
		else if ( $id == 5 )	// Search Channels.
		{
			//$json_array[ "data" ][ "id" ] = 5;
			//$json_array[ "data" ][ "name" ] = "Search";
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
						") ORDER BY " . $sort_type . " ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$guide_ids = [];
				if ( $get_guide_name )
				{
					$query = "SELECT * FROM [guide_id] ORDER BY id ASC";
					$results = $db->query( $query );

					while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
					{
						$guide_ids += [ $arr[ "id" ] => $arr[ "name" ] ];
					}
				}

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
																		  "guide_name" => ( array_key_exists( $row[ "guide_id" ], $guide_ids ) ? $guide_ids[ $row[ "guide_id" ] ] : "" ),
																		  "url" => $row[ "url" ],
																		  "extension" => $row[ "extension" ],
																		  "logo_url" => $row[ "logo_url" ],
																		  "headers" => $headers,
																		  "favorite" => $row[ "favorite" ] ) );
				}
			}
		}
		else
		{
			$total = 0;

			$t_query = " FROM [group] WHERE parent_id = " . $id . " ORDER BY number ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				if( $total > 0 )
				{
					// Get all the groups that fall under the parent ID.
					$range = GetRange( $total, $limit, $offset );
					$query = MakeQuery( $range, "SELECT id, number, name, alias" . $t_query );
					$results = $db->query( $query );

					while ( $row = $results->fetchArray() )
					{
						$name = ( $row[ "alias" ] != NULL ? $row[ "alias" ] : $row[ "name" ] );

						array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																			  "name" => $name ) );
					}
				}
			}

			// If there's no groups, then get all the channels that fall under the group's ID.
			if ( $total == 0 )
			{
				$json_array[ "data" ][ "type" ] = 1;

				$t_query = " FROM [channel] WHERE id IN (SELECT channel_id FROM [group_channel_map] WHERE id = " . $id . ") ORDER BY " . $sort_type . " ASC";

				$query = "SELECT COUNT(*)" . $t_query;
				$results = $db->query( $query );
				if ( $row = $results->fetchArray() )
				{
					$total = $row[ "COUNT(*)" ];

					$json_array[ "data" ][ "total" ] = $total;

					$guide_ids = [];
					if ( $get_guide_name )
					{
						$query = "SELECT * FROM [guide_id] ORDER BY id ASC";
						$results = $db->query( $query );

						while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
						{
							$guide_ids += [ $arr[ "id" ] => $arr[ "name" ] ];
						}
					}

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
																			  "guide_name" => ( array_key_exists( $row[ "guide_id" ], $guide_ids ) ? $guide_ids[ $row[ "guide_id" ] ] : "" ),
																			  "url" => $row[ "url" ],
																			  "extension" => $row[ "extension" ],
																			  "logo_url" => $row[ "logo_url" ],
																			  "headers" => $headers,
																			  "favorite" => $row[ "favorite" ] ) );
					}
				}
			}
			else
			{
				$json_array[ "data" ][ "type" ] = 0;
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
		$json_array[ "data" ][ "id" ] = $id;
		if ( $id == 0 )
		{
			$json_array[ "data" ][ "name" ] = "Movies";
		}
		else
		{
			$results = $db->query( "SELECT name FROM [movie_group] WHERE id=" . $id );
			if ( $row = $results->fetchArray() )
			{
				$json_array[ "data" ][ "name" ] = $row[ "name" ];
			}
		}

		if ( $id == 1 )	// Get all movies.
		{
			$json_array[ "data" ][ "type" ] = 1;

			$t_query = " FROM [movie] ORDER BY name ASC";

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
		else if ( $id == 0 || $id == 2 )	// Get #A-Z groups.
		{
			$json_array[ "data" ][ "type" ] = 0;

			if ( $id == 2 )
			{
				$sort_type = "id";
			}

			$t_query = " FROM [movie_group] WHERE parent_id = " . $id . " ORDER BY " . $sort_type . " ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$range = GetRange( $total, $limit, $offset );
				$query = MakeQuery( $range, "SELECT id, name" . $t_query );
				$results = $db->query( $query );
		
				while ( $row = $results->fetchArray() )
				{
					array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																		  "name" => $row[ "name" ] ) );
				}
			}
		}
		else if ( $id == 3 ) // Get Decades groups.
		{
			$json_array[ "data" ][ "type" ] = 0;

			$group_ids = [];

			$query = "SELECT DISTINCT year-(year%10) FROM [movie] ORDER BY year ASC";
			$results = $db->query( $query );

			while ( $row = $results->fetchArray() )
			{
				array_push( $group_ids, $row[ "year-(year%10)"] );
			}

			$id_list = implode( ",", $group_ids );

			$t_query = " FROM [movie_group] WHERE id IN (" . $id_list . ") ORDER BY id ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$range = GetRange( $total, $limit, $offset );
				$query = MakeQuery( $range, "SELECT id, name" . $t_query );
				$results = $db->query( $query );
		
				while ( $row = $results->fetchArray() )
				{
					array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																		  "name" => $row[ "name" ] ) );
				}
			}
		}
		else if ( $id == 5 )	// Search Movies.
		{
			//$json_array[ "data" ][ "id" ] = 5;
			//$json_array[ "data" ][ "name" ] = "Search";
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
		else if ( $id >= 10 && $id <= 36 )	// Get movies in #A-Z groups.
		{
			$json_array[ "data" ][ "type" ] = 1;
	
			$letter = "#ABCDEFGHIJKLMNOPQRSTUVWXYZ"[ $id - 10 ];

			$t_query1 = " FROM [movie] WHERE name LIKE '" . $letter . "%' OR name LIKE '''" . $letter . "%' ORDER BY name ASC";
			$t_query2 = " FROM [movie] WHERE name NOT GLOB '[a-zA-Z]*' ORDER BY name ASC";

			$query = "";
			if ( $letter != "#" )
			{
				$query = "SELECT COUNT(*)" . $t_query1;
			}
			else
			{
				$query = "SELECT COUNT(*)" . $t_query2;
			}
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$range = GetRange( $total, $limit, $offset );
	
				if ( $letter != "#" )
				{
					$query = MakeQuery( $range, "SELECT *" . $t_query1 );
				}
				else
				{
					$query = MakeQuery( $range, "SELECT *" . $t_query2 );
				}
	
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
		else if ( $id >= 1900 && $id <= 2900 )	// Get movies in Decades groups.
		{
			$json_array[ "data" ][ "type" ] = 1;

			$t_query = " FROM [movie] WHERE year >= " . $id . " AND year <= " . ( $id + 9 ) . " ORDER BY year ASC, name ASC";

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
		else
		{
			$total = 0;

			$t_query = " FROM [movie_group] WHERE parent_id = " . $id . " ORDER BY number ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				if( $total > 0 )
				{
					// Get all the groups that fall under the parent ID.
					$range = GetRange( $total, $limit, $offset );
					$query = MakeQuery( $range, "SELECT id, number, name" . $t_query );
					$results = $db->query( $query );

					while ( $row = $results->fetchArray() )
					{
						array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																			  "name" => $row[ "name" ] ) );
					}
				}
			}

			// If there's no groups, then get all the movies that fall under the group's ID.
			if ( $total == 0 )
			{
				$json_array[ "data" ][ "type" ] = 1;

				$t_query = " FROM [movie] WHERE id IN (SELECT movie_id FROM [group_movie_map] WHERE id = " . $id . ") ORDER BY " . $sort_type . " ASC";

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
			else
			{
				$json_array[ "data" ][ "type" ] = 0;
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
		$json_array[ "data" ][ "id" ] = $id;
		if ( $id == 0 )
		{
			$json_array[ "data" ][ "name" ] = "TV Shows";
		}
		else
		{
			$results = $db->query( "SELECT name FROM [series_group] WHERE id=" . $id );
			if ( $row = $results->fetchArray() )
			{
				$json_array[ "data" ][ "name" ] = $row[ "name" ];
			}
		}

		if ( $id == 1 )	// Get all series groups.
		{
			$json_array[ "data" ][ "type" ] = 0;

			$t_query = " FROM [series_group] WHERE type=1 ORDER BY name ASC";

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
		else if ( $id == 0 || $id == 2 )	// Get #A-Z groups.
		{
			$json_array[ "data" ][ "type" ] = 0;

			$query = "SELECT COUNT(*) FROM [series_group] WHERE parent_id=" . $id;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$range = GetRange( $total, $limit, $offset );

				$query = "SELECT * FROM (SELECT id, name, type, year, season FROM [series_group] WHERE parent_id=" . $id . " AND id<10000 ORDER BY id ASC)";
				$query .= " UNION ALL";
				$query .= " SELECT * FROM (SELECT id, name, type, year, season FROM [series_group] WHERE parent_id=" . $id . " AND id>=10000 ORDER BY name ASC)";

				$query = MakeQuery( $range, $query );
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
		else if ( $id == 3 ) // Get Decades groups.
		{
			$json_array[ "data" ][ "type" ] = 0;

			$group_ids = [];

			$query = "SELECT DISTINCT year-(year%10) FROM [series_group] WHERE type=1 ORDER BY year ASC";
			$results = $db->query( $query );

			while ( $row = $results->fetchArray() )
			{
				array_push( $group_ids, $row[ "year-(year%10)"] );
			}

			$id_list = implode( ",", $group_ids );

			$t_query = " FROM [series_group] WHERE id IN (" . $id_list . ") ORDER BY id ASC";

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
		else if ( $id == 5 )	// Search TV Shows.
		{
			//$json_array[ "data" ][ "id" ] = 5;
			//$json_array[ "data" ][ "name" ] = "Search";
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
		else if ( $id >= 10 && $id <= 36 )	// Get series groups in #A-Z groups.
		{
			$json_array[ "data" ][ "type" ] = 0;
	
			$letter = "#ABCDEFGHIJKLMNOPQRSTUVWXYZ"[ $id - 10 ];

			$t_query1 = " FROM [series_group] WHERE type=1 AND (name LIKE '" . $letter . "%' OR name LIKE '''" . $letter . "%') ORDER BY name ASC";
			$t_query2 = " FROM [series_group] WHERE type=1 AND (name NOT GLOB '[a-zA-Z]*') ORDER BY name ASC";
	
			$query = "";
			if ( $letter != "#" )
			{
				$query = "SELECT COUNT(*)" . $t_query1;
			}
			else
			{
				$query = "SELECT COUNT(*)" . $t_query2;
			}
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				$range = GetRange( $total, $limit, $offset );

				if ( $letter != "#" )
				{
					$query = MakeQuery( $range, "SELECT id, name, type, year, season" . $t_query1 );
				}
				else
				{
					$query = MakeQuery( $range, "SELECT id, name, type, year, season" . $t_query2 );
				}
		
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
		else if ( $id >= 1900 && $id <= 2900 )	// Get series groups in Decades groups.
		{
			$json_array[ "data" ][ "type" ] = 0;

			$t_query = " FROM [series_group] WHERE type=1 AND (year >= " . $id . " AND year <= " . ( $id + 9 ) . ") ORDER BY name ASC";

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
		else
		{
			$total = 0;

			$t_query = " FROM [series_group] WHERE parent_id=" . $id . " ORDER BY type DESC, season ASC";

			$query = "SELECT COUNT(*)" . $t_query;
			$results = $db->query( $query );
			if ( $row = $results->fetchArray() )
			{
				$total = $row[ "COUNT(*)" ];

				$json_array[ "data" ][ "total" ] = $total;

				if( $total > 0 )
				{
					$series_name = "";

					// $id is the parent group.
					$query = "WITH RECURSIVE under_root(id,parent_id,name,type,level) AS ( VALUES(0," . $id . ",'',0,0)";
					$query .= " UNION ALL";
					$query .= " SELECT [series_group].id, [series_group].parent_id, [series_group].name, [series_group].type, under_root.level+1";
					$query .= " FROM [series_group] JOIN under_root ON [series_group].id=under_root.parent_id";
					$query .= " ORDER BY 1 ASC )";
					$query .= " SELECT * FROM under_root WHERE id >= 10000 ORDER BY level DESC;";

					$results = $db->query( $query );

					while ( $row = $results->fetchArray() )
					{
						if ( $row[ "type" ] == 1 )
						{
							$series_name = $row[ "name" ];

							break;
						}
					}

					// Get all the groups that fall under the parent ID.
					$range = GetRange( $total, $limit, $offset );
					$query = MakeQuery( $range, "SELECT id, name, type, year, season" . $t_query );
					$results = $db->query( $query );

					while ( $row = $results->fetchArray() )
					{
						array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																			  "name" => $row[ "name" ],
																			  "series_name" => $series_name,
																			  "type" => $row[ "type" ],
																		  	  "year" => $row[ "year" ],
																		  	  "season" => $row[ "season" ] ) );
					}
				}
			}

			// If there's no groups, then get all the series that fall under the group's ID.
			if ( $total == 0 )
			{
				$json_array[ "data" ][ "type" ] = 1;

				$t_query = " FROM";
				$t_query .= " [series] t1";
				$t_query .= " LEFT JOIN";
				$t_query .= " [series_group] t2";
				$t_query .= " ON t1.series_name_id = t2.id";
				$t_query .= " LEFT JOIN";
				$t_query .= " [series_group] t3";
				$t_query .= " ON t1.season_name_id = t3.id";
				$t_query .= " WHERE t1.id IN (SELECT series_id FROM [group_series_map] WHERE id=" . $id . ")";

				$query = "SELECT COUNT(*)" . $t_query;
				$results = $db->query( $query );
				if ( $row = $results->fetchArray() )
				{
					$total = $row[ "COUNT(*)" ];

					$json_array[ "data" ][ "total" ] = $total;

					$range = GetRange( $total, $limit, $offset );
					$query = MakeQuery( $range, "SELECT t1.id, t1.name AS t1_name, t1.url, t1.extension, t1.season, t1.episode, t1.year AS t1_year, t1.subtitle_url, t1.logo_url, t1.headers, t2.name AS t2_name, t2.year AS t2_year, t3.name AS t3_name, t3.year AS t3_year" . $t_query );

					$results = $db->query( $query );

					while ( $row = $results->fetchArray() )
					{
						$name = ( $row[ "t1_name"] != NULL ? $row[ "t1_name"] : "" );
						$subtitle_url = ( $row[ "subtitle_url" ] != NULL ? $row[ "subtitle_url" ] : "" );
						$headers = ( $row[ "headers" ] != NULL ? $row[ "headers" ] : "" );

						$t2_name = ( $row[ "t2_name"] != NULL ? $row[ "t2_name"] : "" );
						$t3_name = ( $row[ "t3_name"] != NULL ? $row[ "t3_name"] : "" );

						$t2_year = ( $row[ "t2_year"] != NULL ? $row[ "t2_year"] : 0 );
						$t3_year = ( $row[ "t3_year"] != NULL ? $row[ "t3_year"] : 0 );

						array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																			  "series_name" => $t2_name,
																			  "season_name" => $t3_name,
																			  "name" => $name,
																			  "season" => $row[ "season" ],
																			  "episode" => $row[ "episode" ],
																			  "url" => $row[ "url" ],
																			  "extension" => $row[ "extension" ],
																			  "series_year" => $t2_year,
																			  "season_year" => $t3_year,
																			  "year" => $row[ "t1_year" ],
																			  "subtitle_url" => $subtitle_url,
																			  "logo_url" => $row[ "logo_url" ],
																		  	  "headers" => $headers ) );
					}
				}
			}
			else
			{
				$json_array[ "data" ][ "type" ] = 0;
			}
		}
	}
	
	$db->close();
}
else if ( $type == 10 )	// Live TV Channel and its group tree.
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
		$json_array[ "data" ][ "type" ] = 3;
		$json_array[ "data" ][ "total" ] = 1;
		$json_array[ "data" ][ "id" ] = $id;

		$query = "SELECT * FROM [channel] WHERE number=" . $channel_number . " LIMIT 1";
		$results = $db->query( $query );

		$channel_id = -1;

		while ( $row = $results->fetchArray() )
		{
			$channel_id = $row[ "id" ];

			$name = ( $row[ "alias" ] != NULL ? $row[ "alias" ] : $row[ "name" ] );
			$headers = ( $row[ "headers" ] != NULL ? $row[ "headers" ] : "" );

			array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																  "number" => $row[ "number" ],
																  "name" => $name,
																  "guide_id" => $row[ "guide_id" ],
																  "url" => $row[ "url" ],
																  "extension" => $row[ "extension" ],
																  "logo_url" => $row[ "logo_url" ],
																  "headers" => $headers,
																  "favorite" => $row[ "favorite" ] ) );
		}

		if ( $channel_id != -1 )
		{
			$groups = [];

			// The first group to add is always the root group.
			array_push( $groups, array( "id" => 0, "name" => "Live TV" ) );

			if ( $id >= 10000 || $id == 1 )
			{
				// $id is the channel's group.
				$query = "WITH RECURSIVE under_root(id,parent_id,name,alias,level) AS ( VALUES(0," . $id . ",'','',0)";
				$query .= " UNION ALL";
				$query .= " SELECT [group].id, [group].parent_id, [group].name, [group].alias, under_root.level+1";
				$query .= " FROM [group] JOIN under_root ON [group].id=under_root.parent_id";
				$query .= " ORDER BY 1 ASC )";
				$query .= " SELECT * FROM under_root WHERE id >= 10000 AND id != " . $id . " ORDER BY level DESC;";

				$results = $db->query( $query );

				while ( $row = $results->fetchArray() )
				{
					$name = ( $row[ "alias" ] != NULL ? $row[ "alias" ] : $row[ "name" ] );

					array_push( $groups, array( "id" => $row[ "id" ],
												"name" => $name ) );

					if ( $id == $row[ "id" ] )
					{
						$json_array[ "data" ][ "name" ] = $name;
					}
				}
			}
			else
			{
				$json_array[ "data" ][ "id" ] = 0;

				$json_array[ "data" ][ "name" ] = "Live TV";
			}

			$json_array[ "data" ][ "groups" ] = $groups;
		}
	}

	$db->close();
}

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
