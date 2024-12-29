<?php
$type = isset( $_POST[ "type" ] ) ? ( int )$_POST[ "type" ] : -1;
$add_type = isset( $_POST[ "add_type" ] ) ? ( int )$_POST[ "add_type" ] : -1;
$parent_id = isset( $_POST[ "parent_id" ] ) ? ( int )$_POST[ "parent_id" ] : -1;

$name = isset( $_POST[ "name" ] ) ? $_POST[ "name" ] : "";
$guide_name = isset( $_POST[ "guide_name" ] ) ? $_POST[ "guide_name" ] : "";
$url = isset( $_POST[ "url" ] ) ? $_POST[ "url" ] : "";
$extension = isset( $_POST[ "extension" ] ) ? $_POST[ "extension" ] : "";
$season = isset( $_POST[ "season" ] ) ? ( int )$_POST[ "season" ] : 0;
$episode = isset( $_POST[ "episode" ] ) ? ( int )$_POST[ "episode" ] : 0;
$year = isset( $_POST[ "year" ] ) ? ( int )$_POST[ "year" ] : 0;
$subtitle_url = isset( $_POST[ "subtitle_url" ] ) ? $_POST[ "subtitle_url" ] : "";
$logo_url = isset( $_POST[ "logo_url" ] ) ? $_POST[ "logo_url" ] : "";
$headers = isset( $_POST[ "headers" ] ) ? $_POST[ "headers" ] : "";

$group_type = isset( $_POST[ "group_type" ] ) ? ( int )$_POST[ "group_type" ] : 0;
$group_year = isset( $_POST[ "group_year" ] ) ? ( int )$_POST[ "group_year" ] : 0;
$group_season = isset( $_POST[ "group_season" ] ) ? ( int )$_POST[ "group_season" ] : 0;
if ( $group_type != 2 ) // If not a season group.
{
    $group_season = 0;
}

$id = -1;

$ret = -1;

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
		if ( $row[ "name" ] == "group" || $row[ "name" ] == "channel" || $row[ "name" ] == "group_channel_map" || $row[ "name" ] == "guide_id" )
		{
			++$tables_exist;
		}
	}

	if ( $tables_exist == 4 )
	{
        if ( $add_type == 0 )   // Group
        {
            $number = 10000;

            $query = "SELECT MAX(id), MAX(number) FROM [group]";
			$results = $db->query( $query );

			if ( $row = $results->fetchArray() )
			{
                $id = $row[ "MAX(id)" ];
                if ( $id === NULL )
                {
                    $id = 10000;    // Start after the precreated groups All, Favorites, and Search.
                }
                else
                {
                    ++$id;
                }

                $number = $row[ "MAX(number)" ];
                if ( $number === NULL )
                {
                    $number = 10000;
                }
                else
                {
                    ++$number;
                }
            }

            if ( $id != -1 )
            {
                $name = SQLite3::escapeString( $name );

                $query = "INSERT OR IGNORE INTO [group]( id, parent_id, number, name ) VALUES (" . $id . "," .
                                                                                                   $parent_id . "," .
                                                                                                   $number . ",'" .
                                                                                                   $name . "')";
                $results = $db->exec( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $ret = $id;   // The update succeeded.
                }
            }
        }
        else if ( $add_type == 1 )   // Content
        {
            $guide_id = 0;

            if ( $guide_name != "" )
            {
                $guide_name = SQLite3::escapeString( $guide_name );

                $query = "SELECT id FROM [guide_id] WHERE name='" . $guide_name . "'";
                $results = $db->query( $query );

                if ( $arr = $results->fetchArray( SQLITE3_ASSOC ) )
                {
                    $guide_id = $arr[ "id" ];
                }
                else
                {
                    $query = "SELECT MAX(id) FROM [guide_id]";
                    $results = $db->query( $query );

                    if ( $row = $results->fetchArray() )
                    {
                        $guide_id = $row[ "MAX(id)" ];
                        if ( $guide_id === NULL )
                        {
                            $guide_id = 0;
                        }
                        else
                        {
                            ++$guide_id;
                        }

                        $query = "INSERT OR IGNORE INTO [guide_id]( id, name ) VALUES (" . $guide_id . ",'" . $guide_name . "')";
                        $results = $db->query( $query );

                        if ( !$results || $db->changes() <= 0 )
                        {
                            $guide_id = 0;  // Failed.
                        }
                    }
                }
            }


            $number = 1;

            $query = "SELECT MAX(id), MAX(number) FROM [channel]";
			$results = $db->query( $query );

			if ( $row = $results->fetchArray() )
			{
                $id = $row[ "MAX(id)" ];
                if ( $id === NULL )
                {
                    $id = 0;
                }
                else
                {
                    ++$id;
                }

                $number = $row[ "MAX(number)" ];
                if ( $number === NULL )
                {
                    $number = 1;
                }
                else
                {
                    ++$number;
                }
            }

            if ( $id != -1 )
            {
                $name = SQLite3::escapeString( $name );
                $url = SQLite3::escapeString( $url );
                $extension = SQLite3::escapeString( $extension );
                $logo_url = SQLite3::escapeString( $logo_url );
                $headers = SQLite3::escapeString( $headers );

                $query = "INSERT OR IGNORE INTO [channel]( id, number, name, guide_id, url, extension, logo_url, headers )";
                $query .= " VALUES (" . $id . "," .
                                        $number . ",'" .
                                        $name . "'," . 
                                        $guide_id . ",'" .
                                        $url . "','" .
                                        $extension . "','" .
                                        $logo_url . "','" .
                                        $headers . "')";
                $results = $db->exec( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $query = "INSERT OR IGNORE INTO [group_channel_map]( id, channel_id ) VALUES (" . $parent_id . "," . $id . ")";
                    $results = $db->exec( $query );

                    if ( $results && $db->changes() > 0 )
                    {
                        $ret = $id;   // The update succeeded.
                    }
                }
            }
        }
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
        if ( $add_type == 0 )   // Group
        {
            $number = 10000;

            $query = "SELECT MAX(id), MAX(number) FROM [movie_group]";
			$results = $db->query( $query );

			if ( $row = $results->fetchArray() )
			{
                $id = $row[ "MAX(id)" ];
                if ( $id === NULL )
                {
                    $id = 10000;    // Start after the precreated groups All, #A-Z, Decades, and Search.
                }
                else
                {
                    ++$id;
                }

                $number = $row[ "MAX(number)" ];
                if ( $number === NULL )
                {
                    $number = 10000;
                }
                else
                {
                    ++$number;
                }
            }

            if ( $id != -1 )
            {
                // Exclude the precreated groups All, #A-Z, and Decades.
                if ( $id < 10000 )
                {
                    $id = 10000;
                }

                $name = SQLite3::escapeString( $name );

                $query = "INSERT OR IGNORE INTO [movie_group]( id, parent_id, number, name ) VALUES (" . $id . "," .
                                                                                                         $parent_id . "," .
                                                                                                         $number . ",'" .
                                                                                                         $name . "')";
                $results = $db->exec( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $ret = $id;   // The update succeeded.
                }
            }
        }
        else if ( $add_type == 1 )   // Content
        {
            $number = 1;

            $query = "SELECT MAX(id), MAX(number) FROM [movie]";
			$results = $db->query( $query );

			if ( $row = $results->fetchArray() )
			{
                $id = $row[ "MAX(id)" ];
                if ( $id === NULL )
                {
                    $id = 0;
                }
                else
                {
                    ++$id;
                }

                $number = $row[ "MAX(number)" ];
                if ( $number === NULL )
                {
                    $number = 1;
                }
                else
                {
                    ++$number;
                }
            }

            if ( $id != -1 )
            {
                $name = SQLite3::escapeString( $name );
                $url = SQLite3::escapeString( $url );
                $extension = SQLite3::escapeString( $extension );
                $subtitle_url = SQLite3::escapeString( $subtitle_url );
                $logo_url = SQLite3::escapeString( $logo_url );
                $headers = SQLite3::escapeString( $headers );

                $query = "INSERT OR IGNORE INTO [movie]( id, number, name, url, extension, year, subtitle_url, logo_url, headers )";
                $query .= " VALUES (" . $id . "," .
                                        $number . ",'" .
                                        $name . "','" .
                                        $url . "','" .
                                        $extension . "','" .
                                        $year . "','" .
                                        $subtitle_url . "','" .
                                        $logo_url . "','" .
                                        $headers . "')";
                $results = $db->exec( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $query = "INSERT OR IGNORE INTO [group_movie_map]( id, movie_id ) VALUES (" . $parent_id . "," . $id . ")";
                    $results = $db->exec( $query );

                    if ( $results && $db->changes() > 0 )
                    {
                        $ret = $id;   // The update succeeded.
                    }
                }
            }
        }
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
        if ( $add_type == 0 )   // Group
        {
            $number = 10000;

            $query = "SELECT MAX(id), MAX(number) FROM [series_group]";
			$results = $db->query( $query );

			if ( $row = $results->fetchArray() )
			{
                $id = $row[ "MAX(id)" ];
                if ( $id === NULL )
                {
                    $id = 10000;    // Start after the precreated groups All, #A-Z, Decades, and Search.
                }
                else
                {
                    ++$id;
                }

                $number = $row[ "MAX(number)" ];
                if ( $number === NULL )
                {
                    $number = 10000;
                }
                else
                {
                    ++$number;
                }
            }

            if ( $id != -1 )
            {
                // Exclude the precreated groups All, #A-Z, and Decades.
                if ( $id < 10000 )
                {
                    $id = 10000;
                }

                $name = SQLite3::escapeString( $name );

                $query = "INSERT OR IGNORE INTO [series_group]( id, parent_id, number, name, type, year, season ) VALUES (" . $id . "," .
                                                                                                                              $parent_id . "," .
                                                                                                                              $number . ",'" .
                                                                                                                              $name . "'," .
                                                                                                                              $group_type . "," .
                                                                                                                              $group_year . "," .
                                                                                                                              $group_season . ")";
                $results = $db->exec( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $ret = $id;   // The update succeeded.
                }
            }
        }
        else if ( $add_type == 1 )   // Content
        {
            $number = 1;

            $query = "SELECT MAX(id), MAX(number) FROM [series]";
			$results = $db->query( $query );

			if ( $row = $results->fetchArray() )
			{
                $id = $row[ "MAX(id)" ];
                if ( $id === NULL )
                {
                    $id = 0;
                }
                else
                {
                    ++$id;
                }

                $number = $row[ "MAX(number)" ];
                if ( $number === NULL )
                {
                    $number = 1;
                }
                else
                {
                    ++$number;
                }
            }

            if ( $id != -1 )
            {
                $series_name_id = -1;
                $season_name_id = -1;

                $query = "WITH RECURSIVE under_root(id,parent_id,type,level) AS ( VALUES(0," . $parent_id . ",0,0)";
                $query .= " UNION ALL";
                $query .= " SELECT [series_group].id, [series_group].parent_id, [series_group].type, under_root.level+1";
                $query .= " FROM [series_group] JOIN under_root ON [series_group].id=under_root.parent_id";
                $query .= " ORDER BY 1 ASC )";
                $query .= " SELECT * FROM under_root WHERE id >= 10000 ORDER BY level DESC;";

                $results = $db->query( $query );

                while ( $row = $results->fetchArray() )
                {
                    if ( $row[ "type" ] == 1 )
                    {
                        $series_name_id = $row[ "id" ];
                    }
                    else if ( $row[ "type" ] == 2 )
                    {
                        $season_name_id = $row[ "id" ];
                    }
                }

                $name = SQLite3::escapeString( $name );
                $url = SQLite3::escapeString( $url );
                $extension = SQLite3::escapeString( $extension );
                $subtitle_url = SQLite3::escapeString( $subtitle_url );
                $logo_url = SQLite3::escapeString( $logo_url );
                $headers = SQLite3::escapeString( $headers );

                $query = "INSERT OR IGNORE INTO [series]( id, number, series_name_id, season_name_id, name, url, extension, year, season, episode, subtitle_url, logo_url, headers )";
                $query .= " VALUES (" . $id . "," .
                                        $number . "," .
                                        $series_name_id . "," .
                                        $season_name_id . ",'" .
                                        $name . "','" .
                                        $url . "','" .
                                        $extension . "'," .
                                        $year . "," .
                                        $season . "," .
                                        $episode . ",'" .
                                        $subtitle_url . "','" .
                                        $logo_url . "','" .
                                        $headers . "')";
                $results = $db->exec( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $query = "INSERT OR IGNORE INTO [group_series_map]( id, series_id ) VALUES (" . $parent_id . "," . $id . ")";
                    $results = $db->exec( $query );

                    if ( $results && $db->changes() > 0 )
                    {
                        $ret = $id;   // The update succeeded.
                    }
                }
            }
        }
    }

    $db->close();
}

echo $ret;
?>
