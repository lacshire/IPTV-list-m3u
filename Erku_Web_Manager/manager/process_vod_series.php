<?php
$start_time = microtime( true );

$total_items_added = 0;
$total_items = 0;

$group_id = 9999;
$group_number = 9999;

$database_path = "../databases/";

//$channels_file_path = $database_path . "channels.db";
$vod_file_path = $database_path . "vod.db";

$playlist_file_path = $database_path . "playlist.m3u";

function GetGroups( $arr, $results, $level )
{
	global $group_id;
	global $group_number;

	$group = [];
	$last_arr = $arr;

	while ( true )
	{
		if ( $arr[ "level" ] == $level )
		{
			$group_id = $arr[ "id" ];
			// Save the highest group number.
			if ( $arr[ "number" ] > $group_number )
			{
				$group_number = $arr[ "number" ];
			}
			$name = $arr[ "name" ];
			if ( $arr[ "type" ] == 2 )
			{
				$name = "Season: " . $arr[ "season" ];
			}
			$group += [ $name => array( "in_database" => true,
										"id" => $group_id,
										"parent_id" => $arr[ "parent_id" ],
										"number" => $arr[ "number" ],
										"type" => $arr[ "type" ],
										"year" => $arr[ "year" ],
										"season" => $arr[ "season" ],
										"series_id_array" => [],
										"sub_group" => [] ) ];
		}
		else if ( $arr[ "level" ] > $level )
		{
			$sub_group = GetGroups( $arr, $results, $arr[ "level" ] );
			$group[ $last_arr[ "name" ] ][ "sub_group" ] += $sub_group[ "group" ];

			if ( $sub_group[ "arr" ] )
			{
				$arr = $sub_group[ "arr" ];
				if ( $arr[ "level" ] != $level )
				{
					break;
				}
				else
				{
					continue;
				}
			}
			else
			{
				break;
			}
		}
		else
		{
			break;
		}

		$last_arr = $arr;

		if ( !( $arr = $results->fetchArray( SQLITE3_ASSOC ) ) )
		{
			break;
		}
	}

	return [ "arr" => $arr, "group" => $group ];
}

$handle = fopen( $playlist_file_path, "r" );
if ( $handle )
{
	$groups = [];

	$vods = [];
	$id = -1;

	$url_ids = [];

	$names_ids = [];
	$names_id = -1;

	$db_vod = new SQLite3( $vod_file_path );

	// Does our group list exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='series_group'";
	$results = $db_vod->query( $query );
	if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		// Preserve the tree structure. It's possible that a child group was added after other groups were added. It's ID would not be in order. This maintains that.
		$query = "WITH RECURSIVE tree_view AS (SELECT id, parent_id, number, name, type, year, season, 0 AS level, CAST(id AS varchar(50)) AS order_sequence";
		$query .= " FROM [series_group]";
		$query .= " WHERE parent_id IS 0 AND id >= 10000";
		$query .= " UNION ALL";
		$query .= " SELECT parent.id, parent.parent_id, parent.number, parent.name, parent.type, parent.year, parent.season, level + 1 AS level, CAST(order_sequence || '_' || CAST(parent.id AS VARCHAR(50)) AS VARCHAR(50)) AS order_sequence";
		$query .= " FROM [series_group] parent";
		$query .= " JOIN tree_view tv";
		$query .= " ON parent.parent_id = tv.id )";
		$query .= " SELECT * FROM tree_view ORDER BY order_sequence;";

		$results = $db_vod->query( $query );

		$arr = $results->fetchArray( SQLITE3_ASSOC );
		if ( $arr )
		{
			$sub_group = GetGroups( $arr, $results, 0 );
			$groups = $sub_group[ "group" ];
		}
	}
	else
	{
		goto _EXIT;
	}

	// Does our series list exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='series'";
	$results = $db_vod->query( $query );
	if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		$query = "SELECT * FROM [series] ORDER BY id ASC";
		$results = $db_vod->query( $query );

		while ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
		{
			$id = $arr[ "id" ];

			$url_id = $arr[ "url" ];

			if ( !array_key_exists( $url_id, $url_ids ) )
			{
				$url_ids += [ $url_id => NULL ];
			}
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

				$is_series = false; 
				$dir = strtok( parse_url( $url, PHP_URL_PATH ), '/' );
				if ( $dir == "series" )
				{
					$is_series = true;
				}

				if ( $is_series )
				{
					$year = 0;
					$extension = NULL;
					$logo_url = NULL;
					$name = NULL;

					$season = 0;
					$episode = 0;

					//

					$tvg_logo_offset = strpos( $line, "tvg-logo=\"", 7 );
					$group_title_offset = strpos( $line, "group-title=\"", 7 );

					$tvg_logo_open_quote = false;
					$group_title_open_quote = false;

					// Get the VOD Logo.
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

					//$country_or_language = NULL;

					// Get the VOD's Group Title.
					if ( $group_title_offset !== false )
					{
						$group_title_offset += 13;
						$end = strpos( $line, "\"", $group_title_offset );
						if ( $end !== false )
						{
							/*$group = substr( $line, $group_title_offset, $end - $group_title_offset );

							$country_matches = NULL;
							$ret = preg_match('/\[[a-zA-Z]+\]/', $group, $country_matches );
							if ( $ret === 1 )
							{
								$country_or_language = $country_matches[ 0 ];
								$country_or_language = substr( $country_or_language, 1, strlen( $country_or_language ) - 2 );
							}*/

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

					$name_start = max( $tvg_logo_offset, $group_title_offset );
					if ( $name_start == $group_title_offset ) { $open_quote = $group_title_open_quote; }
					else if ( $name_start == $tvg_logo_offset ) { $open_quote = $tvg_logo_open_quote; }

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

					//$language = NULL;

					// Try to get the VOD's Language from its name.
					$language_end = strpos( $name, " -" );
					if ( $language_end !== false )
					{
						//$language = substr( $name, 0, $language_end );

						$language_end += 2;
					}
					else
					{
						$language_end = 0;
					}

					// This can be used to filter out langauges.
					/*if ( ( $language != NULL && $language != "EN" && $language != "A+" && $language != "D+" ) ||
						 ( $country_or_language != NULL && $country_or_language != "EN" && $country_or_language != "MULTISUB" ) ||
						 ( $language == NULL && $country_or_language == NULL ) )
					{
						continue;
					}*/

					$name_start = $language_end;
					$name_end = strlen( $name );

					$matches = NULL;
					if ( preg_match( '/\W\(/', $name, $matches, PREG_OFFSET_CAPTURE ) !== false )
					{
						if ( count( $matches ) > 0 )
						{
							if ( $matches[ 0 ][ 1 ] < $name_end )
							{
								$name_end = $matches[ 0 ][ 1 ];
							}
						}
					}

					$matches = NULL;
					if ( preg_match_all( '/\(\d{4}[-\)\(]/', $name, $matches, PREG_OFFSET_CAPTURE ) !== false )
					{
						$last_item = count( $matches[ 0 ] ) - 1;
						if ( $last_item >= 0 )
						{
							$year = intval( substr( $name, $matches[ 0 ][ $last_item ][ 1 ] + 1, 4 ) );
							
							if ( $matches[ 0 ][ $last_item ][ 1 ] < $name_end )
							{
								$name_end = $matches[ 0 ][ $last_item ][ 1 ];
							}
						}
					}

					$matches = NULL;
					if ( preg_match_all( '/[Ss]\d{2,3}/', $name, $matches, PREG_OFFSET_CAPTURE ) !== false )
					{
						$last_item = count( $matches[ 0 ] ) - 1;
						if ( $last_item >= 0 )
						{
							$season = intval( substr( $matches[ 0 ][ $last_item ][ 0 ], 1 ) );

							if ( $matches[ 0 ][ $last_item ][ 1 ] < $name_end )
							{
								$name_end = $matches[ 0 ][ $last_item ][ 1 ];
							}
						}
					}

					$matches = NULL;
					if ( preg_match_all( '/[Ee]\d{2,3}/', $name, $matches, PREG_OFFSET_CAPTURE ) !== false )
					{
						$last_item = count( $matches[ 0 ] ) - 1;
						if ( $last_item >= 0 )
						{
							$episode = intval( substr( $matches[ 0 ][ $last_item ][ 0 ], 1 ) );

							if ( $matches[ 0 ][ $last_item ][ 1 ] < $name_end )
							{
								$name_end = $matches[ 0 ][ $last_item ][ 1 ];
							}
						}
					}

					if ( $name_end > $name_start )
					{
						$name = trim( substr( $name, $name_start, $name_end - $name_start ) );
					}
					else
					{
						$name = trim( substr( $name, $name_start ) );
					}

					// Groups
        			if ( !array_key_exists( $name, $groups ) )
					{
						$groups += [ $name => array( "in_database" => false,
													 "id" => ++$group_id,
													 "parent_id" => 0,
													 "number" => ++$group_number,
													 "type" => 1,
													 "year" => $year,
													 "season" => 0,
													 "series_id_array" => [],
													 "sub_group" => [] ) ];
					}

					if ( !array_key_exists( "Season: " . $season, $groups[ $name ][ "sub_group" ] ) )
					{
						$groups[ $name ][ "sub_group" ] += [ "Season: " . $season => array( "in_database" => false,
																							"id" => ++$group_id,
																							"parent_id" => $groups[ $name ][ "id" ],
																							"number" => ++$group_number,
																							"type" => 2,
																							"year" => $year,
																							"season" => $season,
																							"series_id_array" => [],
																							"sub_group" => [] ) ];
					}

					$url_id = $url;

					++$total_items;

					// Only add unique URLs.
					if ( !array_key_exists( $url_id, $url_ids ) )
					{
						++$total_items_added;

						$parsed_url = parse_url( $url );
						$extension = pathinfo( $parsed_url[ "path" ], PATHINFO_EXTENSION );

						$url_ids += [ $url_id => NULL ];

						++$id;

						array_push( $vods, array( "id" => $id,
												  "number" => $id + 1,
												  "series_name_id" => $groups[ $name ][ "id" ],
												  "season_name_id" => $groups[ $name ][ "sub_group" ][ "Season: " . $season ][ "id" ],
												  "url" => $url,
												  "extension" => $extension,
												  "season" => $season,
												  "episode" => $episode,
												  "logo_url" => $logo_url ) );

						array_push( $groups[ $name ][ "sub_group" ][ "Season: " . $season ][ "series_id_array" ], $id );
					}
				}
			}
		}
	}

	fclose( $handle );

	// Add the groups to the channels.db file.
	// Series group:        type = 1
	// Season group:        type = 2
	// All other groups:    type = NULL 
	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [series_group]( id, parent_id, number, name, type, year, season ) VALUES ";
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

			$query .= "(" . $group_value[ "id" ] . "," .
							$group_value[ "parent_id" ] . "," .
							$group_value[ "number" ] . ",'" .
							$name . "'," .
							$group_value[ "type" ] . "," .
							$group_value[ "year" ] . "," .
							$group_value[ "season" ] . ")";

			++$chunk;
			if ( $chunk >= 5000 )
			{
				$chunk = 0;
				$results = $db_vod->query( $query );
				$query = "";
			}
		}

		foreach ( $group_value[ "sub_group" ] as $sub_group => $sub_group_value )
		{
			if ( !$sub_group_value[ "in_database" ] )
			{
				if ( $query != "" )
				{
					$query .= ",";
				}
				else
				{
					$query = $chunk_query;
				}

				$name = "";

				$query .= "(" . $sub_group_value[ "id" ] . "," .
								$sub_group_value[ "parent_id" ] . "," .
								$sub_group_value[ "number" ] . ",'" .
								$name . "'," .
								$sub_group_value[ "type" ] . "," .
								$sub_group_value[ "year" ] . "," .
								$sub_group_value[ "season" ] . ")";

				++$chunk;
				if ( $chunk >= 5000 )
				{
					$chunk = 0;
					$results = $db_vod->query( $query );
					$query = "";
				}
			}
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_vod->query( $query );
	}

	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [series]( id, number, series_name_id, season_name_id, url, extension, season, episode, logo_url ) VALUES ";
	$chunk = 0;

	foreach ( $vods as $vod )
	{
		if ( $query != "" )
		{
			$query .= ",";
		}
		else
		{
			$query = $chunk_query;
		}

		$url = SQLite3::escapeString( $vod[ "url" ] );
		$extension = SQLite3::escapeString( $vod[ "extension" ] );
		$logo_url = SQLite3::escapeString( $vod[ "logo_url" ] );
		$query .= "(" . $vod[ "id" ] . "," .
						$vod[ "number" ] . "," .
						$vod[ "series_name_id" ] . "," .
						$vod[ "season_name_id" ] . ",'" .
						$url . "','" .
						$extension . "'," .
						$vod[ "season" ] . "," .
						$vod[ "episode" ] . ",'" .
						$logo_url . "')";

		++$chunk;
		if ( $chunk >= 5000 )
		{
			$chunk = 0;
			$results = $db_vod->query( $query );
			$query = "";
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_vod->query( $query );
	}

	// Add the group-series map to the vod.db
	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [group_series_map]( id, series_id ) VALUES ";
	$chunk = 0;

	foreach ( $groups as $group => $group_value )
	{
		foreach ( $group_value[ "sub_group" ] as $sub_group => $sub_group_value )
		{
			$channel_count = count( $sub_group_value[ "series_id_array" ] );
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

				$query .= "('" . $sub_group_value[ "id" ] . "','" .
								 $sub_group_value[ "series_id_array" ][ $i ] . "')";

				++$chunk;
				if ( $chunk >= 5000 )
				{
					$chunk = 0;
					$results = $db_vod->query( $query );
					$query = "";
				}
			}
		}
	}

	if ( $chunk > 0 )
	{
		$results = $db_vod->query( $query );
	}

_EXIT:

	$db_vod->close();
}

$json_array = [ "elapsed_time" => ( microtime( true ) - $start_time ), "total_items" => $total_items, "total_items_added" => $total_items_added ];

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
