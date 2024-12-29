<?php
$database_path = "../databases/";

$channels_file_path = $database_path . "channels.db";
$vod_file_path = $database_path . "vod.db";

$json_array = [ "channels_modification_time" => 0, "vod_modification_time" => 0, "playlist_modification_time" => 0, "epg_modification_time" => 0 ];

if ( file_exists( $database_path . $channels_file_path ) )
{
    $json_array[ "channels_modification_time" ] = filemtime( $database_path . $channels_file_path );
}
if ( file_exists( $database_path . $vod_file_path ) )
{
    $json_array[ "vod_modification_time" ] = filemtime( $database_path . $vod_file_path );
}

if ( file_exists( $database_path . "playlist.m3u" ) )
{
    $json_array[ "playlist_modification_time" ] = filemtime( $database_path . "playlist.m3u" );
}
if ( file_exists( $database_path . "epg.xml" ) )
{
    $json_array[ "epg_modification_time" ] = filemtime( $database_path . "epg.xml" );
}

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
