<?php
$start_time = microtime( true );

$total_items_added = 0;
$total_items = 0;

$time_zone = "America/Los_Angeles";
$date = new DateTime();
$current_time = $date->getTimestamp();

$guide_ids = [];

$database_path = "../databases/";

$channels_file_path = $database_path . "channels.db";
//$vod_file_path = $database_path . "vod.db";
$epg_file_path = $database_path . "epg.xml";

$db_channels = new SQLite3( $channels_file_path );

// Does our guide id map exist?
$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='guide_id'";
$results = $db_channels->query( $query );
if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
{
	$query = "SELECT * FROM [guide_id]";
	$results = $db_channels->query( $query );

	while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		$guide_ids += [ $arr[ "name" ] => $arr[ "id" ] ];
	}
}
else
{
	goto _EXIT;
}

// Does our epg exist?
$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='epg'";
$results = $db_channels->query( $query );
if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
{
	$query = "DELETE FROM [epg] WHERE stop <= " . $current_time;
	$results = $db_channels->query( $query );
}
else
{
	goto _EXIT;
}

$xml = new XMLReader();
$xml->open( $epg_file_path );

$query = "";
$chunk_query = "INSERT OR IGNORE INTO [epg]( id, title, start, stop, description ) VALUES ";
$chunk = 0;

while ( @$xml->read() )
{
	if ( $xml->nodeType == XMLReader::ELEMENT && $xml->name === "programme" )
	{
		++$total_items;

		$guide_id = $xml->getAttribute( "channel" );
		if ( !array_key_exists( $guide_id, $guide_ids ) )
		{
			continue;
		}

		$stop = $xml->getAttribute( "stop" );
		$date = $date->createFromFormat('YmdHis O', $stop, new DateTimeZone( "UTC" ) );
		$date = $date->setTimeZone( new DateTimeZone( $time_zone ) );
		$stop_ts = $date->getTimestamp();
		if ( $stop_ts <= $current_time )
		{
			continue;
		}

		$start = $xml->getAttribute( "start" );
		$date = $date->createFromFormat('YmdHis O', $start, new DateTimeZone( "UTC" ) );
		$date = $date->setTimeZone( new DateTimeZone( $time_zone ) );
		$start_ts = $date->getTimestamp();
		if ( $stop_ts <= $start_ts )
		{
			continue;
		}

		$inner_xml = $xml->readOuterXml();

		$sxml = simplexml_load_string( $inner_xml );
		
		$title = SQLite3::escapeString( $sxml->title );
		$description = SQLite3::escapeString( $sxml->desc );

		if ( $query != "" )
		{
			$query .= ",";
		}
		else
		{
			$query = $chunk_query;
		}
		$query .= "(" . $guide_ids[ $guide_id ] . ",'" . $title . "'," . $start_ts . "," . $stop_ts . ",'" . $description . "')";
		
		++$chunk;
		if ( $chunk >= 5000 )
		{
			$chunk = 0;
			$results = $db_channels->query( $query );
			$query = "";
		}

		++$total_items_added;
	}
}

if ( $chunk > 0 )
{
	$chunk = 0;
	$results = $db_channels->query( $query );
}

$xml->close();

_EXIT:

//$db_channels->query( "VACUUM" );
$db_channels->close();

$json_array = [ "elapsed_time" => ( microtime( true ) - $start_time ), "total_items" => $total_items, "total_items_added" => $total_items_added ];

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
