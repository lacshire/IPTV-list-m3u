<?php
$start_time = microtime( true );

$type = isset( $_POST[ "type" ] ) ? ( int )$_POST[ "type" ] : -1;
$url = isset( $_POST[ "url" ] ) ? $_POST[ "url" ] : "";

$is_cmd = false;

if ( $type != 0 && $type != 1 && $url == "" && $argc == 5 )
{
    $is_cmd = true;

    $arguments = [ $argv[ 1 ] => $argv[ 2 ], $argv[ 3 ] => $argv[ 4 ] ];

    $type = isset( $arguments[ "--type" ] ) ? ( int )$arguments[ "--type" ] : -1;
    $url = isset( $arguments[ "--url" ] ) ? $arguments[ "--url" ] : "";
}

$database_path = "../databases/";

$status = -1;

$total_downloaded = 0;

function progress_callback( $ch, $dltotal, $dlnow, $ultotal, $ulnow )
{
    echo "\r" . $dlnow . "/" . $dltotal . " bytes";
}

if ( ( $type == 0 || $type == 1 ) && $url != "" )
{
    $filename = "playlist.m3u";
    if ( $type == 1 )
    {
        $filename = "epg.xml";
    }

    $fp = fopen( $database_path . $filename, "w+" );

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
    curl_setopt( $ch, CURLOPT_FILE, $fp ); 
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
    if ( $is_cmd )
    {
        curl_setopt( $ch, CURLOPT_NOPROGRESS, 0 );
        curl_setopt( $ch, CURLOPT_PROGRESSFUNCTION, "progress_callback" );
    }
    curl_exec( $ch ); 

    if ( !curl_errno( $ch ) )
    {
        $total_downloaded = curl_getinfo( $ch, CURLINFO_SIZE_DOWNLOAD );

        $status = 1;
    }

    curl_close( $ch );

    fclose( $fp );
}
$elapsed_time = microtime( true ) - $start_time;
if ( $is_cmd )
{
    echo "\r\nDownload finished in " . $elapsed_time . " seconds.";
}
else
{
    $json_array = [ "elapsed_time" => $elapsed_time, "total" => $total_downloaded, "status" => $status ];

    header( "Access-Control-Allow-Origin: *" );
    echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
}
?>
