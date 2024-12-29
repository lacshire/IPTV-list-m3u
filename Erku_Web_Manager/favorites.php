<?php
$type = isset( $_REQUEST[ "type" ] ) ? ( int )$_REQUEST[ "type" ] : -1;
$id = isset( $_REQUEST[ "id" ] ) ? ( int )$_REQUEST[ "id" ] : -1;

$ret = -1;

$database_path = "databases/";

$channels_file_path = $database_path . "channels.db";

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
    if ( $type == 0 )    // Remove
    {
        $query = "UPDATE [channel] SET favorite='0' WHERE id=" . $id . ";";
        $results = $db->exec( $query );

        if ( $results && $db->changes() > 0 )
        {
            $ret = 1;   // The update succeeded.
        }
    }
    else if ( $type == 1 )  // Add
    {
        $query = "UPDATE [channel] SET favorite='1' WHERE id=" . $id . ";";
        $results = $db->exec( $query );

        if ( $results && $db->changes() > 0 )
        {
            $ret = 1;   // The update succeeded.
        }
    }
}

$db->close();

header( "Access-Control-Allow-Origin: *" );
echo $ret;
?>
