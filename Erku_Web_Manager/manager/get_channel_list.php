<?php
// type: 0 = groups, 1 = channels
$json_array = [ "data" => array( "type" => -1, "total" => 0, "id" => -1, "name" => "", "values" => [] ) ];

$database_path = "../databases/";

$channels_file_path = $database_path . "channels.db";
//$vod_file_path = $database_path . "vod.db";

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
    $json_array[ "data" ][ "id" ] = 1;  // All Channels.
    $json_array[ "data" ][ "name" ] = "Live TV";
    $json_array[ "data" ][ "type" ] = 1;

    $query = "SELECT COUNT(*) FROM [channel]";
    $results = $db->query( $query );
    if ( $row = $results->fetchArray() )
    {
        $json_array[ "data" ][ "total" ] = $row[ "COUNT(*)" ];

        $guide_ids = [];

        $query = "SELECT * FROM [guide_id] ORDER BY id ASC";
        $results = $db->query( $query );

        while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
        {
            $guide_ids += [ $arr[ "id" ] => $arr[ "name" ] ];
        }

        $query = "SELECT t1.id AS t1_id, t1.number AS t1_number, t1.name AS t1_name, t1.guide_id AS t1_guide_id, t1.url AS t1_url, t1.extension AS t1_extension, t1.logo_url AS t1_logo_url, t1.headers AS t1_headers FROM [channel] t1";
        $query .= " LEFT JOIN";
            $query .= " (SELECT * FROM [group_channel_map] t11";
            $query .= " LEFT JOIN(";
                $query .= " SELECT DISTINCT t111.id, t111.number FROM [group] t111";
                $query .= " LEFT JOIN";
                $query .= " [group_channel_map] t222";
                $query .= " WHERE t111.id = t222.id) AS t22";
            $query .= " WHERE t11.id = t22.id";
            $query .= " ORDER BY number) AS t2";
        $query .= " WHERE t1.id = t2.channel_id";
        $query .= " ORDER BY t1.number";
        $results = $db->query( $query );

        while ( $row = $results->fetchArray() )
        {
            $name = ( $row[ "t1_alias" ] != NULL ? $row[ "t1_alias" ] : $row[ "t1_name" ] );
            $headers = ( $row[ "t1_headers" ] != NULL ? $row[ "t1_headers" ] : "" );

            array_push( $json_array[ "data" ][ "values" ], array( "id" => $row[ "t1_id" ],
                                                                  "number" => $row[ "t1_number" ],
                                                                  "name" => $name,
                                                                  "guide_name" => ( array_key_exists( $row[ "t1_guide_id" ], $guide_ids ) ? $guide_ids[ $row[ "t1_guide_id" ] ] : "" ),
                                                                  "url" => $row[ "t1_url" ],
                                                                  "extension" => $row[ "t1_extension" ],
                                                                  "logo_url" => $row[ "t1_logo_url" ],
                                                                  "headers" => $headers ) );
        }

        $query = "SELECT * FROM [channel] WHERE id NOT IN (SELECT channel_id FROM group_channel_map) ORDER BY id ASC";
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
																  "headers" => $headers ) );
        }
    }
}

$db->close();

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
