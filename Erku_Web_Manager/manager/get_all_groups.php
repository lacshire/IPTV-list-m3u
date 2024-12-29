<?php
$type = isset( $_POST[ "type" ] ) ? ( int )$_POST[ "type" ] : -1;
$id = isset( $_POST[ "id" ] ) ? ( int )$_POST[ "id" ] : -1;

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
		if ( $row[ "name" ] == "group" || $row[ "name" ] == "channel" || $row[ "name" ] == "group_channel_map" )
		{
			++$tables_exist;
		}
	}

	if ( $tables_exist == 3 )
	{
        $content_count_array = [];

        $query = "SELECT id, COUNT(channel_id) FROM [group_channel_map] GROUP BY id";
        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $content_count_array += [ $row[ "id" ] => $row[ "COUNT(channel_id)" ] ];
        }

        $query = "SELECT * FROM [group] WHERE parent_id=" . $id . " AND id >= 3 ORDER BY number ASC";

        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $name = ( $row[ "alias" ] != NULL ? $row[ "alias" ] : $row[ "name" ] );

            $count = 0;
            if ( array_key_exists( $row[ "id" ], $content_count_array ) )
            {
                $count = $content_count_array[ $row[ "id" ] ];
            }
            array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
                                                                  "parent_id" => $row[ "parent_id" ],
                                                                  "name" => $name,
                                                                  "content_count" => $count ) );
        }

        $json_array[ "data" ][ "type" ] = 0;
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
        $content_count_array = [];

        $query = "SELECT id, COUNT(movie_id) FROM [group_movie_map] GROUP BY id";
        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $content_count_array += [ $row[ "id" ] => $row[ "COUNT(movie_id)" ] ];
        }

        $query = "SELECT * FROM [movie_group] WHERE parent_id=" . $id . " AND id >= 10000 ORDER BY number ASC";

        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $count = 0;
            if ( array_key_exists( $row[ "id" ], $content_count_array ) )
            {
                $count = $content_count_array[ $row[ "id" ] ];
            }
            array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
                                                                  "parent_id" => $row[ "parent_id" ],
                                                                  "name" => $row[ "name" ],
                                                                  "content_count" => $count ) );
        }

        $json_array[ "data" ][ "type" ] = 0;
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
        $content_count_array = [];

        $query = "SELECT id, COUNT(series_id) FROM [group_series_map] GROUP BY id";
        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $content_count_array += [ $row[ "id" ] => $row[ "COUNT(series_id)" ] ];
        }

        if ( $id == 0 )
        {
            $query = " SELECT * FROM [series_group] WHERE parent_id=0 AND id >= 10000 ORDER BY number ASC";

            $results = $db->query( $query );

            while ( $row = $results->fetchArray() )
            {
                $count = 0;
                if ( array_key_exists( $row[ "id" ], $content_count_array ) )
                {
                    $count = $content_count_array[ $row[ "id" ] ];
                }
                array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
                                                                      "parent_id" => $row[ "parent_id" ],
                                                                      "name" => $row[ "name" ],
                                                                      "type" => $row[ "type" ],
                                                                      "year" => $row[ "year" ],
                                                                      "season" => $row[ "season" ],
                                                                      "content_count" => $count ) );
            }
        }
        else
        {
            $query = "SELECT * FROM [series_group] WHERE parent_id=" . $id . " AND id >= 10000 ORDER BY number ASC";

            $results = $db->query( $query );

            while ( $row = $results->fetchArray() )
            {
                $count = 0;
                if ( array_key_exists( $row[ "id" ], $content_count_array ) )
                {
                    $count = $content_count_array[ $row[ "id" ] ];
                }
                array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "id" ],
                                                                      "parent_id" => $row[ "parent_id" ],
                                                                      "name" => $row[ "name" ],
                                                                      "type" => $row[ "type" ],
                                                                      "year" => $row[ "year" ],
                                                                      "season" => $row[ "season" ],
                                                                      "content_count" => $count ) );
            }
        }

        $json_array[ "data" ][ "type" ] = 0;
    }

    $db->close();
}
header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
