<?php
$type = isset( $_REQUEST[ "type" ] ) ? ( int )$_REQUEST[ "type" ] : -1;

// type: 0 = groups, 1 = channels
$json_array = [ "data" => array( "type" => -1, "values" => [] ) ];

$database_path = "../databases/";

$channels_file_path = $database_path . "channels.db";
$vod_file_path = $database_path . "vod.db";

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
        $json_array[ "data" ][ "type" ] = 1;

		$guide_ids = [];

		$query = "SELECT * FROM [guide_id] ORDER BY id ASC";
		$results = $db->query( $query );

		while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
		{
			$guide_ids += [ $arr[ "id" ] => $arr[ "name" ] ];
		}

        $query = "SELECT * FROM [channel] WHERE id NOT IN (SELECT channel_id FROM group_channel_map) ORDER BY id ASC";
        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $name = ( $row[ "alias" ] != NULL ? $row[ "alias" ] : $row[ "name" ] );
			$headers = ( $row[ "headers" ] != NULL ? $row[ "headers" ] : "" );

            array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																  "name" => $name,
																  "guide_name" => ( array_key_exists( $row[ "guide_id" ], $guide_ids ) ? $guide_ids[ $row[ "guide_id" ] ] : "" ),
																  "url" => $row[ "url" ],
																  "extension" => $row[ "extension" ],
																  "logo_url" => $row[ "logo_url" ],
																  "headers" => $headers ) );
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
        $json_array[ "data" ][ "type" ] = 1;

        $query = "SELECT * FROM [movie] WHERE id NOT IN (SELECT movie_id FROM [group_movie_map]) ORDER BY name ASC";
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
																  "subtitle_url" => $row[ "subtitle_url" ],
																  "logo_url" => $row[ "logo_url" ],
																  "headers" => $headers ) );
        }
    }

    $db->close();
}
else if ( $type == 2 )	// TV Shows
{
	$db = new SQLite3( $vod_file_path );

	$tables_exist = 0;

	// Do our tables exist?
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
        $json_array[ "data" ][ "type" ] = 1;

		$query = "SELECT * FROM [series] WHERE id NOT IN (SELECT series_id FROM [group_series_map]) ORDER BY name ASC";
        $results = $db->query( $query );

		while ( $row = $results->fetchArray() )
		{
			$name = ( $row[ "name"] != NULL ? $row[ "name"] : "" );
			$subtitle_url = ( $row[ "subtitle_url" ] != NULL ? $row[ "subtitle_url" ] : "" );
			$headers = ( $row[ "headers" ] != NULL ? $row[ "headers" ] : "" );

			array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
																  "name" => $name,
																  "season" => $row[ "season" ],
																  "episode" => $row[ "episode" ],
																  "url" => $row[ "url" ],
																  "extension" => $row[ "extension" ],
																  "year" => $row[ "year" ],
																  "subtitle_url" => $row[ "subtitle_url" ],
																  "logo_url" => $row[ "logo_url" ],
																  "headers" => $headers ) );
		}
    }

    $db->close();
}
header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
