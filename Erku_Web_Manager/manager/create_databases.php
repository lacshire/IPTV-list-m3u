<?php
$start_time = microtime( true );

$database_path = "../databases/";

$channels_file_path = $database_path . "channels.db";
$vod_file_path = $database_path . "vod.db";

$db = new SQLite3( $channels_file_path );

$query = "CREATE TABLE IF NOT EXISTS [group]( id INTEGER PRIMARY KEY, " .
                                             "parent_id INTEGER, " .
                                             "number INTEGER, " .
                                             "name TEXT, " .
                                             "alias TEXT )";
$results = $db->query( $query );

    $query = "INSERT OR IGNORE INTO [group]( id, parent_id, number, name ) VALUES ( 1, 0, 1, 'All' ), ( 4, 0, 4, 'Favorites' ), ( 5, 0, 5, 'Search' )";
    $results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [channel]( id INTEGER PRIMARY KEY, " .
                                               "number INTEGER, " .
                                               "name TEXT, " .
                                               "alias TEXT, " .
                                               "guide_id INTEGER, " .
                                               "url TEXT, " .
                                               "extension TEXT, " .
                                               "logo_url TEXT, " .
                                               "headers TEXT, " .
                                               "favorite INTEGER DEFAULT 0 )";
$results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [group_channel_map]( id INTEGER, " .
                                                         "channel_id INTEGER )";
$results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [guide_id]( id INTEGER PRIMARY KEY, " .
                                                "name TEXT UNIQUE )";
$results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [epg]( id INTEGER, " .
                                           "title TEXT, " .
                                           "start TIMESTAMP, " .
                                           "stop TIMESTAMP, " .
                                           "description TEXT, " .
                                           "UNIQUE( id, start, stop ) )";
$results = $db->query( $query );

$db->close();

chmod( $channels_file_path, 0777 );

//

$db = new SQLite3( $vod_file_path );

$query = "CREATE TABLE IF NOT EXISTS [movie_group]( id INTEGER PRIMARY KEY, " .
                                                   "parent_id INTEGER, " .
                                                   "number INTEGER, " .
                                                   "name TEXT )";
$results = $db->query( $query );

    $query = "INSERT OR IGNORE INTO [movie_group]( id, parent_id, number, name ) VALUES ( 1, 0, 1, 'All' ), ( 2, 0, 2, '#A-Z' ), ( 3, 0, 3, 'Decades' ), ( 5, 0, 5, 'Search' )";
    $results = $db->query( $query );

    $num_alpha_id = 10;
    $query = "INSERT OR IGNORE INTO [movie_group]( id, parent_id, number, name ) VALUES ( " . $num_alpha_id . ", 2," . $num_alpha_id . ", '#' )";
    foreach ( range( 'A', 'Z' ) as $name )
    {
        ++$num_alpha_id;

        $query .= ",(" . $num_alpha_id . ",2," . $num_alpha_id . ",'" . $name . "')";
    }
    $results = $db->query( $query );

    $decade = 1900;
    $query = "INSERT OR IGNORE INTO [movie_group]( id, parent_id, number, name ) VALUES ";
    for ( $i = 0; $i < 20; ++$i )
    {
        $query .= "(" . $decade . ",3," . $decade . ",'" . $decade . "s')";

        if ( $i < 19 )
        {
            $query .= ",";

            $decade += 10;
        }
    }
    $results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [group_movie_map]( id INTEGER, " .
                                                       "movie_id INTEGER )";
$results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [movie]( id INTEGER PRIMARY KEY, " .
                                             "number INTEGER, " .
                                             "name TEXT, " .
                                             "url TEXT, " .
                                             "extension TEXT, " .
                                             "year INTEGER DEFAULT 0, " .
                                             "subtitle_url TEXT, " .
                                             "logo_url TEXT, " .
                                             "headers TEXT )";
$results = $db->query( $query );

//

// Series group:        type = 1
// Season group:        type = 2
// All other groups:    type = 0 
$query = "CREATE TABLE IF NOT EXISTS [series_group]( id INTEGER PRIMARY KEY, " .
                                                    "parent_id INTEGER, " .
                                                    "number INTEGER, " .
                                                    "name TEXT, " .
                                                    "type INTEGER DEFAULT 0, " .
                                                    "year INTEGER DEFAULT 0, " .
                                                    "season INTEGER DEFAULT 0 )";
$results = $db->query( $query );

    $query = "INSERT OR IGNORE INTO [series_group]( id, parent_id, number, name ) VALUES ( 1, 0, 1, 'All' ), ( 2, 0, 2, '#A-Z' ), ( 3, 0, 3, 'Decades' ), ( 5, 0, 5, 'Search' )";
    $results = $db->query( $query );

    $num_alpha_id = 10;
    $query = "INSERT OR IGNORE INTO [series_group]( id, parent_id, number, name ) VALUES ( " . $num_alpha_id . ", 2," . $num_alpha_id . ", '#' )";
    foreach ( range( 'A', 'Z' ) as $name )
    {
        ++$num_alpha_id;

        $query .= ",(" . $num_alpha_id . ",2," . $num_alpha_id . ",'" . $name . "')";
    }
    $results = $db->query( $query );

    $decade = 1900;
    $query = "INSERT OR IGNORE INTO [series_group]( id, parent_id, number, name ) VALUES ";
    for ( $i = 0; $i < 20; ++$i )
    {
        $query .= "(" . $decade . ",3," . $decade . ",'" . $decade . "s')";

        if ( $i < 19 )
        {
            $query .= ",";

            $decade += 10;
        }
    }
    $results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [group_series_map]( id INTEGER, " .
                                                        "series_id INTEGER )";
$results = $db->query( $query );

$query = "CREATE TABLE IF NOT EXISTS [series]( id INTEGER PRIMARY KEY, " .
                                              "number INTEGER, " .
                                              "series_name_id INTEGER, " .
                                              "season_name_id INTEGER, " .
                                              "name TEXT, " .
                                              "url TEXT, " .
                                              "extension TEXT, " .
                                              "season INTEGER, " .
                                              "episode INTEGER, " .
                                              "year INTEGER DEFAULT 0, " .
                                              "subtitle_url TEXT, " .
                                              "logo_url TEXT, " .
                                              "headers TEXT )";
$results = $db->query( $query );

$db->close();

chmod( $vod_file_path, 0777 );

$json_array = [ "elapsed_time" => ( microtime( true ) - $start_time ) ];

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
