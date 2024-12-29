<?php
$start_time = microtime( true );

$total_items_added = 0;
$total_items = 0;

$database_path = "../databases/";

$channels_file_path = $database_path . "channels.db";
//$vod_file_path = $database_path . "vod.db";
$playlist_file_path = $database_path . "playlist.m3u";

$handle = fopen( $playlist_file_path, "r" );
if ( $handle )
{
	$groups = [];
	$group_id = 9999;
	$group_number = 9999;

	$channels = [];
	$id = -1;

	$guide_ids = [];
	$guide_id = 0;

	$url_ids = [];

	$db_channels = new SQLite3( $channels_file_path );

	// Does our group list exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='group'";
	$results = $db_channels->query( $query );
	if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		$query = "SELECT * FROM [group] WHERE id >= 10000 ORDER BY id ASC";
		$results = $db_channels->query( $query );

		while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
		{
			$group_id = $arr[ "id" ];
			// Save the highest group number.
			if ( $arr[ "number" ] > $group_number )
			{
				$group_number = $arr[ "number" ];
			}
			$groups += [ $arr[ "name" ] => array( "in_database" => true,
												  "id" => $group_id,
												  "parent_id" => $arr[ "parent_id" ],
												  "number" => $arr[ "number" ],
												  "channel_id_array" => [] ) ];
		}
	}
	else
	{
		goto _EXIT;
	}

	// Does our channel list exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='channel'";
	$results = $db_channels->query( $query );
	if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		$query = "SELECT * FROM [channel] ORDER BY id ASC";
		$results = $db_channels->query( $query );

		while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
		{
			$url_id = $arr[ "url" ];

			if ( !array_key_exists( $url_id, $url_ids ) )
			{
				$id = $arr[ "id" ];

				$url_ids += [ $url_id => NULL ];
			}
		}
	}
	else
	{
		goto _EXIT;
	}

	// Does our guide id map exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='guide_id'";
	$results = $db_channels->query( $query );
	if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		$query = "SELECT * FROM [guide_id] ORDER BY id ASC";
		$results = $db_channels->query( $query );

		while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
		{
			$guide_id = $arr[ "id" ];
			$guide_ids += [ $arr[ "name" ] => $arr[ "id" ] ];
		}
	}
	else
	{
		goto _EXIT;
	}

	while ( ( $line = fgets( $handle ) ) !== false )
	{
		if ( strncmp( $line, "#EXTINF", 7 ) == 0 )
		{
			$url = fgets( $handle );

			while ( $url !== false && strncmp( $url, "#", 1 ) == 0 )
			{
				$url = fgets( $handle );
			}

			if ( $url !== false )
			{
				$url = trim( $url );

				$parsed_url = parse_url( $url, PHP_URL_PATH );
				if ( $parsed_url != NULL )
				{
					$dir = strtok( $parsed_url, '/' );
					if ( $dir != "movie" && $dir != "series" )
					{
						$logo_url = NULL;
						$name = NULL;
						$group = NULL;
						$guide_id_name = NULL;

						//

						$tvg_id_offset = strpos( $line, "tvg-id=\"", 7 );
						$tvg_logo_offset = strpos( $line, "tvg-logo=\"", 7 );
						$group_title_offset = strpos( $line, "group-title=\"", 7 );

						$tvg_id_open_quote = false;
						$tvg_logo_open_quote = false;
						$group_title_open_quote = false;

						// Get the Channel's Guide ID.
						if ( $tvg_id_offset !== false )
						{
							$tvg_id_offset += 8;
							$end = strpos( $line, "\"", $tvg_id_offset );
							if ( $end !== false )
							{
								$guide_id_name = substr( $line, $tvg_id_offset, $end - $tvg_id_offset );

								$tvg_id_offset = $end + 1;
							}
							else
							{
								$tvg_id_open_quote = true;
							}
						}
						else
						{
							$tvg_id_offset = 0;
						}

						// Get the Channel Logo.
						if ( $tvg_logo_offset !== false )
						{
							$tvg_logo_offset += 10;
							$end = strpos( $line, "\"", $tvg_logo_offset );
							if ( $end !== false )
							{
								$logo_url = substr( $line, $tvg_logo_offset, $end - $tvg_logo_offset );

								$tvg_logo_offset = $end + 1;
							}
							else
							{
								$tvg_logo_open_quote = true;
							}
						}
						else
						{
							$tvg_logo_offset = 0;
						}

						// Get the Channels's Group Title.
						if ( $group_title_offset !== false )
						{
							$group_title_offset += 13;
							$end = strpos( $line, "\"", $group_title_offset );
							if ( $end !== false )
							{
								$group_length = $end - $group_title_offset;
								if ( $group_length > 0 )
								{
									$group = substr( $line, $group_title_offset, $group_length );
								}
								else
								{
									$group = "Other";
								}

								$group_title_offset = $end + 1;
							}
							else
							{
								$group_title_open_quote = true;
							}
						}
						else
						{
							$group_title_offset = 0;
						}

						$open_quote = false;

						$name_start = max( $tvg_id_offset, $tvg_logo_offset, $group_title_offset );
						if ( $name_start == $group_title_offset ) { $open_quote = $group_title_open_quote; }
						else if ( $name_start == $tvg_logo_offset ) { $open_quote = $tvg_logo_open_quote; }
						else if ( $name_start == $tvg_id_offset ) { $open_quote = $tvg_id_open_quote; }

						while ( $line[ $name_start ] != NULL )
						{
							if ( $line[ $name_start ] == '"' )
							{
								$open_quote = !$open_quote;
							}
							else if ( $line[ $name_start ] == ',' && !$open_quote )
							{
								++$name_start;
								break;
							}

							++$name_start;
						}

						$name = trim( substr( $line, $name_start ) );

						// Groups
						if ( !array_key_exists( $group, $groups ) )
						{
							$groups += [ $group => array( "in_database" => false,
														  "id" => ++$group_id,
														  "parent_id" => 0,
														  "number" => ++$group_number,
														  "channel_id_array" => [] ) ];
						}

						$url_id = $url;

						++$total_items;

						// Only add unique URLs.
						if ( !array_key_exists( $url_id, $url_ids ) )
						{
							++$total_items_added;

							$parsed_url = parse_url( $url );
							$extension = pathinfo( $parsed_url[ "path" ], PATHINFO_EXTENSION );
							if ( $extension == "" )
							{
								$extension = "ts";	// Assume Transport Stream.
							}

							$url_ids += [ $url_id => NULL ];
							
							++$id;
							
							$_guide_id = 0;
							if ( $guide_id_name != "" && !array_key_exists( $guide_id_name, $guide_ids ) )
							{
								$_guide_id = ++$guide_id;
								$guide_ids += [ $guide_id_name => $_guide_id ];
							}

							array_push( $channels, array( "id" => $id,
														  "number" => $id + 1,
														  "name" => $name,
														  "guide_id" => $_guide_id,
														  "url" => $url,
														  "extension" => $extension,
														  "logo_url" => $logo_url ) );

							array_push( $groups[ $group ][ "channel_id_array" ], $id );
						}
					}
				}
			}
		}
	}

	fclose( $handle );

	// Add the groups to the channels.db file.
	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [group]( id, parent_id, number, name ) VALUES ";
	$chunk = 0;

	foreach ( $groups as $group => $group_value )
	{
		if ( !$group_value[ "in_database" ] )
		{
			if ( $query != "" )
			{
				$query .= ",";
			}
			else
			{
				$query = $chunk_query;
			}

			$name = SQLite3::escapeString( $group );

			$query .= "(" . $group_value[ "id" ] . "," . $group_value[ "parent_id" ] . "," . $group_value[ "number" ] . ",'" . $name . "')";

			++$chunk;
			if ( $chunk >= 5000 )
			{
				$chunk = 0;
				$results = $db_channels->query( $query );
				$query = "";
			}
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_channels->query( $query );
	}

	// Add the channels to the channels.db file.
	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [channel]( id, number, name, guide_id, url, extension, logo_url ) VALUES ";
	$chunk = 0;

	foreach ( $channels as $channel )
	{
		if ( $query != "" )
		{
			$query .= ",";
		}
		else
		{
			$query = $chunk_query;
		}

		$name = SQLite3::escapeString( $channel[ 'name' ] );
		$url = SQLite3::escapeString( $channel[ 'url' ] );
		$extension = SQLite3::escapeString( $channel[ 'extension' ] );
		$logo_url = SQLite3::escapeString( $channel[ 'logo_url' ] );
		$query .= "('" . $channel[ 'id' ] . "','" . $channel[ 'number' ] . "','" . $name . "','" . $channel[ 'guide_id' ] . "','" . $url . "','" . $extension . "','" . $logo_url . "')";
		
		++$chunk;
		if ( $chunk >= 5000 )
		{
			$chunk = 0;
			$results = $db_channels->query( $query );
			$query = "";
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_channels->query( $query );
	}

	// Add the group-channel map to the channels.db
	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [group_channel_map]( id, channel_id ) VALUES ";
	$chunk = 0;

	foreach ( $groups as $group => $group_value )
	{
		$channel_count = count( $group_value[ "channel_id_array" ] );
		for ( $i = 0; $i < $channel_count; ++$i )
		{
			if ( $query != "" )
			{
				$query .= ",";
			}
			else
			{
				$query = $chunk_query;
			}

			$query .= "('" . $group_value[ "id" ] . "','" . $group_value[ "channel_id_array" ][ $i ] . "')";

			++$chunk;
			if ( $chunk >= 5000 )
			{
				$chunk = 0;
				$results = $db_channels->query( $query );
				$query = "";
			}
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_channels->query( $query );
	}

	// Add the guide IDs to the epg.db file.
	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [guide_id]( id, name ) VALUES ";
	$chunk = 0;

	foreach ( $guide_ids as $guide_id_name => $guide_id_value )
	{
		if ( $guide_id_name != "" )
		{
			if ( $query != "" )
			{
				$query .= ",";
			}
			else
			{
				$query = $chunk_query;
			}

			$guide_id_name = SQLite3::escapeString( $guide_id_name );
			$query .= "('" . $guide_id_value . "','" . $guide_id_name . "')";
			
			++$chunk;
			if ( $chunk >= 5000 )
			{
				$chunk = 0;
				$results = $db_channels->query( $query );
				$query = "";
			}
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_channels->query( $query );
	}

_EXIT:

	$db_channels->close();
}

$json_array = [ "elapsed_time" => ( microtime( true ) - $start_time ), "total_items" => $total_items, "total_items_added" => $total_items_added ];

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
