<?php
$start_time = microtime( true );

$total_items_added = 0;
$total_items = 0;

$database_path = "../databases/";

//$channels_file_path = $database_path . "channels.db";
$vod_file_path = $database_path . "vod.db";

$playlist_file_path = $database_path . "playlist.m3u";

$handle = fopen( $playlist_file_path, "r" );
if ( $handle )
{

	$vods = [];
	$id = -1;

	$url_ids = [];

	$db_vod = new SQLite3( $vod_file_path );

	// Does our group list exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='movie_group'";
	$results = $db_vod->query( $query );
	$arr = $results->fetchArray( SQLITE3_ASSOC );
	if ( $arr == false )
	{
		goto _EXIT;
	}

	// Does our movie list exist?
	$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='movie'";
	$results = $db_vod->query( $query );
	if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
	{
		$query = "SELECT * FROM [movie] ORDER BY id ASC";
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
			
				$is_movie = false;

				$dir = strtok( parse_url( $url, PHP_URL_PATH ), '/' );
				if ( $dir == "movie" )
				{
					$is_movie = true;
				}

				if ( $is_movie )
				{
					$year = 0;
					$extension = NULL;
					$logo_url = NULL;
					$name = NULL;

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

					$matches = NULL;
					if ( preg_match_all( '/\(\d{4}\)/', $name, $matches, PREG_OFFSET_CAPTURE ) !== false )
					{
						$last_item = count( $matches[ 0 ] ) - 1;
						if ( $last_item >= 0 )
						{
							$year = intval( substr( $name, $matches[ 0 ][ $last_item ][ 1 ] + 1, 4 ) );
							$name = trim( substr( $name, $language_end, ( $matches[ 0 ][ $last_item ][ 1 ] - $language_end ) ) );
						}
						else
						{
							$name = trim( substr( $name, $language_end ) );
						}
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
												  "name" => $name,
												  "url" => $url,
												  "extension" => $extension,
												  "year" => $year,
												  "logo_url" => $logo_url ) );
					}
				}
			}
		}
	}

	fclose( $handle );

	$query = "";
	$chunk_query = "INSERT OR IGNORE INTO [movie]( id, number, name, url, extension, year, logo_url ) VALUES ";
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

		$name = SQLite3::escapeString( $vod[ "name" ] );
		$url = SQLite3::escapeString( $vod[ "url" ] );
		$extension = SQLite3::escapeString( $vod[ "extension" ] );
		$logo_url = SQLite3::escapeString( $vod[ "logo_url" ] );
		$query .= "(" . $vod[ "id" ] . "," .
						$vod[ "number" ] . ",'" .
						$name . "','" .
						$url . "','" .
						$extension . "'," .
						$vod[ "year" ] . ",'" .
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

_EXIT:

	$db_vod->close();
}

$json_array = [ "elapsed_time" => ( microtime( true ) - $start_time ), "total_items" => $total_items, "total_items_added" => $total_items_added ];

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
