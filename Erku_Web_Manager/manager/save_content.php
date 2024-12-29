<?php
$type = isset( $_POST[ "type" ] ) ? ( int )$_POST[ "type" ] : -1;
$save_type = isset( $_POST[ "save_type" ] ) ? ( int )$_POST[ "save_type" ] : -1;
$id = isset( $_POST[ "id" ] ) ? ( int )$_POST[ "id" ] : -1;

// If $save_type == 1 or $save_type == 3, then $ids is an array of associative arrays.
// It contains the ID of the group/content and it's parent group ID.
// For everything else it's just an array of strings.
// $id is the parent group ID that we're moving the $ids into.
$ids = isset( $_POST[ "ids" ] ) ? json_decode( $_POST[ "ids" ], true ) : [];

//$series_name = isset( $_POST[ "series_name" ] ) ? $_POST[ "series_name" ] : "";
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
        if ( $row[ "name" ] == "group" || $row[ "name" ] == "channel" || $row[ "name" ] == "group_channel_map" || $row[ "name" ] == "guide_id"  )
        {
            ++$tables_exist;
        }
    }

    if ( $tables_exist == 4 )
    {
        if ( $save_type == 0 )  // Group name update.
        {
            $name = SQLite3::escapeString( $name );

            // Set the alias if name is not null.
			$query = "UPDATE [group] SET alias='" . $name . "' WHERE name IS NOT NULL AND id=" . $id;
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }

            // Set the name if it's null.
			$query = "UPDATE [group] SET name='" . $name . "' WHERE name IS NULL AND id=" . $id;
            $results = $db->exec( $query );

			if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
		else if ( $save_type == 1 )	// Group move.
		{
            $id_list = "";
            $id_count = count( $ids );
            for ( $i = 0; $i < $id_count; ++$i )
            {
                if ( $id_list != "" )
                {
                    $id_list .= ",";
                }

                $id_list .= $ids[ $i ][ "id" ];
            }

            $query = "UPDATE [group] SET parent_id=" . $id . " WHERE id IN (" . $id_list/*implode( ",", $ids )*/ . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
		}
        else if ( $save_type == 2 ) // Content update.
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

            $name = SQLite3::escapeString( $name );
            $url = SQLite3::escapeString( $url );
            $extension = SQLite3::escapeString( $extension );
            $logo_url = SQLite3::escapeString( $logo_url );
            $headers = SQLite3::escapeString( $headers );

            $query = "UPDATE [channel] SET name='" . $name .
                                       "', guide_id=" . $guide_id .
                                        ", url='" . $url .
                                       "', extension='" . $extension .
                                       "', logo_url='" . $logo_url .
                                       "', headers='" . $headers . "' WHERE id=" . $id;
            $results = $db->exec( $query );

			if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }

            // Clean up the guide_id table.
            $query = "DELETE FROM [guide_id] WHERE id NOT IN (SELECT guide_id FROM [channel])";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The delete succeeded.
            //}
        }
        else if ( $save_type == 3 ) // Content move.
        {
            $query = "";
            $chunk_query = "DELETE FROM [group_channel_map] WHERE ";
            $chunk = 0;

            foreach ( $ids as $id_value )
            {
                if ( $query != "" )
                {
                    $query .= " OR ";
                }
                else
                {
                    $query = $chunk_query;
                }

                $query .= "(id=" . $id_value[ "parent_id" ] . " AND channel_id=" . $id_value[ "id" ] . ")";

                ++$chunk;
                if ( $chunk >= 5000 )
                {
                    $chunk = 0;
                    $results = $db->query( $query );
                    $query = "";
                }
            }

            if ( $chunk > 0 )
            {
                $results = $db->query( $query );
            }

            if ( $id != -1 )
            {
                $query = "";
                $chunk_query = "INSERT OR IGNORE INTO [group_channel_map]( id, channel_id ) VALUES ";
                $chunk = 0;

                foreach ( $ids as $id_value )
                {
                    if ( $query != "" )
                    {
                        $query .= ",";
                    }
                    else
                    {
                        $query = $chunk_query;
                    }

                    $query .= "('" . $id . "','" . $id_value[ "id" ] . "')";

                    ++$chunk;
                    if ( $chunk >= 5000 )
                    {
                        $chunk = 0;
                        $results = $db->query( $query );
                        $query = "";
                    }
                }

                if ( $chunk > 0 )
                {
                    $results = $db->query( $query );
                }
            }

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 4 ) // Group remove.
        {
            $group_ids = [];
            $id_count = count( $ids );

            for ( $i = 0; $i < $id_count; ++$i )
            {
                $query = "WITH RECURSIVE under_root(id,level) AS ( VALUES(" . $ids[ $i ] . ",0)";
                $query .= " UNION ALL";
                $query .= " SELECT [group].id, under_root.level+1";
                $query .= " FROM [group] JOIN under_root ON [group].parent_id=under_root.id";
                $query .= " ORDER BY 1 ASC )";
                $query .= " SELECT id FROM under_root;";

                $results = $db->query( $query );

                while ( $row = $results->fetchArray() )
                {
                    array_push( $group_ids, $row[ "id" ] );
                }
            }

            $query = "DELETE FROM [group_channel_map] WHERE id IN (" . implode( ",", $group_ids ) . ")";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The update succeeded.
            //}

            $query = "DELETE FROM [group] WHERE id IN (" . implode( ",", $group_ids ) . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 5 ) // Content remove.
        {
            $query = "DELETE FROM [group_channel_map] WHERE channel_id IN (" . implode( ",", $ids ) . ")";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The delete succeeded.
            //}

            $query = "DELETE FROM [channel] WHERE id IN (" . implode( ",", $ids ) . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The delete succeeded.
            }

            // Clean up the guide_id table.
            $query = "DELETE FROM [guide_id] WHERE id NOT IN (SELECT guide_id FROM [channel])";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The delete succeeded.
            //}
        }
        else if ( $save_type == 6 || $save_type == 7 )  // Save Group/Content Order.
        {
            $number_offset = 10000;
            $table_type = "group";
            if ( $save_type == 7 )
            {
                $number_offset = 1;
                $table_type = "channel";
            }

            $query = "";
            $chunk_query = "UPDATE [" . $table_type . "] SET number= CASE ";
            $chunk = 0;

            $i = 0;
            $id_list = "";
            $id_count = count( $ids );
            for ( ; $i < $id_count; ++$i )
            {
                if ( $id_list != "" )
                {
                    $id_list .= ",";
                }

                $id_list .= $ids[ $i ];

                if ( $query == "" )
                {
                    $query = $chunk_query;
                }

                $query .= " WHEN id=" . $ids[ $i ] . " THEN " . $number_offset + $i;
                
                ++$chunk;
                if ( $chunk >= 5000 )
                {
                    $query .= " END WHERE id IN(" . $id_list . ")";
                    $id_list = "";

                    $chunk = 0;
                    $results = $db->query( $query );
                    $query = "";

                    if ( $results && $db->changes() > 0 )
                    {
                        $ret = 1;
                    }
                }
            }

            if ( $chunk > 0 )
            {
                $query .= " END WHERE id IN(" . $id_list . ")";

                $results = $db->query( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $ret = 1;
                }
            }
        }
        else if ( $save_type == 8 ) // Save Channel Numbers.
        {
            $channel_number = 0;
            $values = "";
            foreach ( $ids as $channel_id )
            {
                if ( $channel_number > 0 )
                {
                    $values .= ",";
                }
                $values .= "(" . $channel_id . "," . ++$channel_number . ")";
            }

            $query = "INSERT INTO [channel](id, number) VALUES " . $values;
            $query .= " ON CONFLICT (id) DO UPDATE SET number=excluded.number";

            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;
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
        if ( $save_type == 0 )  // Group name update.
        {
            $name = SQLite3::escapeString( $name );

            // Set the name.
			$query = "UPDATE [movie_group] SET name='" . $name . "' WHERE id=" . $id;
            $results = $db->exec( $query );

			if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
		else if ( $save_type == 1 )	// Group move.
		{
            $id_list = "";
            $id_count = count( $ids );
            for ( $i = 0; $i < $id_count; ++$i )
            {
                if ( $id_list != "" )
                {
                    $id_list .= ",";
                }

                $id_list .= $ids[ $i ][ "id" ];
            }

            $query = "UPDATE [movie_group] SET parent_id=" . $id . " WHERE id IN (" . $id_list/*implode( ",", $ids )*/ . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
		}
        else if ( $save_type == 2 ) // Content update.
        {
            $name = SQLite3::escapeString( $name );
            $url = SQLite3::escapeString( $url );
            $extension = SQLite3::escapeString( $extension );
            $subtitle_url = SQLite3::escapeString( $subtitle_url );
            $logo_url = SQLite3::escapeString( $logo_url );
            $headers = SQLite3::escapeString( $headers );

            $query = "UPDATE [movie] SET name='" . $name .
                                     "', url='" . $url .
                                     "', extension='" . $extension .
                                     "', year=" . $year .
                                      ", subtitle_url='" . $subtitle_url .
                                     "', logo_url='" . $logo_url .
                                     "', headers='" . $headers . "' WHERE id=" . $id;
            $results = $db->exec( $query );

			if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 3 ) // Content move.
        {
            $query = "";
            $chunk_query = "DELETE FROM [group_movie_map] WHERE ";
            $chunk = 0;

            foreach ( $ids as $id_value )
            {
                if ( $query != "" )
                {
                    $query .= " OR ";
                }
                else
                {
                    $query = $chunk_query;
                }

                $query .= "(id=" . $id_value[ "parent_id" ] . " AND movie_id=" . $id_value[ "id" ] . ")";

                ++$chunk;
                if ( $chunk >= 5000 )
                {
                    $chunk = 0;
                    $results = $db->query( $query );
                    $query = "";
                }
            }

            if ( $chunk > 0 )
            {
                $results = $db->query( $query );
            }

            if ( $id != -1 )
            {
                $query = "";
                $chunk_query = "INSERT OR IGNORE INTO [group_movie_map]( id, movie_id ) VALUES ";
                $chunk = 0;

                foreach ( $ids as $id_value )
                {
                    if ( $query != "" )
                    {
                        $query .= ",";
                    }
                    else
                    {
                        $query = $chunk_query;
                    }

                    $query .= "('" . $id . "','" . $id_value[ "id" ] . "')";

                    ++$chunk;
                    if ( $chunk >= 5000 )
                    {
                        $chunk = 0;
                        $results = $db->query( $query );
                        $query = "";
                    }
                }

                if ( $chunk > 0 )
                {
                    $results = $db->query( $query );
                }
            }

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 4 ) // Group remove.
        {
            $group_ids = [];
            $id_count = count( $ids );

            for ( $i = 0; $i < $id_count; ++$i )
            {
                $query = "WITH RECURSIVE under_root(id,level) AS ( VALUES(" . $ids[ $i ] . ",0)";
                $query .= " UNION ALL";
                $query .= " SELECT [movie_group].id, under_root.level+1";
                $query .= " FROM [movie_group] JOIN under_root ON [movie_group].parent_id=under_root.id";
                $query .= " ORDER BY 1 ASC )";
                $query .= " SELECT id FROM under_root;";

                $results = $db->query( $query );

                while ( $row = $results->fetchArray() )
                {
                    array_push( $group_ids, $row[ "id" ] );
                }
            }

            $query = "DELETE FROM [group_movie_map] WHERE id IN (" . implode( ",", $group_ids ) . ")";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The update succeeded.
            //}

            $query = "DELETE FROM [movie_group] WHERE id IN (" . implode( ",", $group_ids ) . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 5 ) // Content remove.
        {
            $query = "DELETE FROM [group_movie_map] WHERE movie_id IN (" . implode( ",", $ids ) . ")";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The delete succeeded.
            //}

            $query = "DELETE FROM [movie] WHERE id IN (" . implode( ",", $ids ) . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The delete succeeded.
            }
        }
        else if ( $save_type == 6 || $save_type == 7 )  // Save Group/Content Order.
        {
            $number_offset = 10000;
            $table_type = "movie_group";
            if ( $save_type == 7 )
            {
                $number_offset = 1;
                $table_type = "movie";
            }

            $query = "";
            $chunk_query = "UPDATE [" . $table_type . "] SET number= CASE ";
            $chunk = 0;

            $i = 0;
            $id_list = "";
            $id_count = count( $ids );
            for ( ; $i < $id_count; ++$i )
            {
                if ( $id_list != "" )
                {
                    $id_list .= ",";
                }

                $id_list .= $ids[ $i ];

                if ( $query == "" )
                {
                    $query = $chunk_query;
                }

                $query .= " WHEN id=" . $ids[ $i ] . " THEN " . $number_offset + $i;
                
                ++$chunk;
                if ( $chunk >= 5000 )
                {
                    $query .= " END WHERE id IN(" . $id_list . ")";
                    $id_list = "";

                    $chunk = 0;
                    $results = $db->query( $query );
                    $query = "";

                    if ( $results && $db->changes() > 0 )
                    {
                        $ret = 1;
                    }
                }
            }

            if ( $chunk > 0 )
            {
                $query .= " END WHERE id IN(" . $id_list . ")";

                $results = $db->query( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $ret = 1;
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
        if ( $save_type == 0 )  // Group name and type update.
        {
            $name = SQLite3::escapeString( $name );

            // Set the name.
			$query = "UPDATE [series_group] SET name='" . $name . 
                                            "', type=" . $group_type .
                                             ", year=" . $group_year .
                                             ", season=" . $group_season . " WHERE id=" . $id;
            $results = $db->exec( $query );

			if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
		else if ( $save_type == 1 )	// Group move.
		{
            $id_list = "";
            $id_count = count( $ids );
            for ( $i = 0; $i < $id_count; ++$i )
            {
                if ( $id_list != "" )
                {
                    $id_list .= ",";
                }

                $id_list .= $ids[ $i ][ "id" ];
            }

            $query = "UPDATE [series_group] SET parent_id=" . $id . " WHERE id IN (" . $id_list/*implode( ",", $ids )*/ . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
		}
        else if ( $save_type == 2 ) // Content update.
        {
            $name = SQLite3::escapeString( $name );
            $url = SQLite3::escapeString( $url );
            $extension = SQLite3::escapeString( $extension );
            $subtitle_url = SQLite3::escapeString( $subtitle_url );
            $logo_url = SQLite3::escapeString( $logo_url );
            $headers = SQLite3::escapeString( $headers );

            $query = "UPDATE [series] SET name='" . $name .
                                      "', url='" . $url .
                                      "', extension='" . $extension .
                                      "', year=" . $year .
                                       ", season=" . $season .
                                       ", episode=" . $episode .
                                       ", subtitle_url='" . $subtitle_url .
                                      "', logo_url='" . $logo_url .
                                      "', headers='" . $headers . "' WHERE id=" . $id;
            $results = $db->exec( $query );

			if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 3 ) // Content move.
        {
            $query = "";
            $chunk_query = "DELETE FROM [group_series_map] WHERE ";
            $chunk = 0;

            foreach ( $ids as $id_value )
            {
                if ( $query != "" )
                {
                    $query .= " OR ";
                }
                else
                {
                    $query = $chunk_query;
                }

                $query .= "(id=" . $id_value[ "parent_id" ] . " AND series_id=" . $id_value[ "id" ] . ")";

                ++$chunk;
                if ( $chunk >= 5000 )
                {
                    $chunk = 0;
                    $results = $db->query( $query );
                    $query = "";
                }
            }

            if ( $chunk > 0 )
            {
                $results = $db->query( $query );
            }

            if ( $id != -1 )
            {
                $series_name_id = -1;
                $season_name_id = -1;

                // $id is the group we're moving the content into.
                $query = "WITH RECURSIVE under_root(id,parent_id,type,level) AS ( VALUES(0," . $id . ",0,0)";
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

                $id_list = "";
                $id_count = count( $ids );
                for ( $i = 0; $i < $id_count; ++$i )
                {
                    if ( $id_list != "" )
                    {
                        $id_list .= ",";
                    }

                    $id_list .= $ids[ $i ][ "id" ];
                }

                $query = "UPDATE [series] SET series_name_id=" . $series_name_id . ", season_name_id=" . $season_name_id . " WHERE id IN (" . $id_list/*implode( ",", $ids )*/ . ")";
                $results = $db->exec( $query );


                $query = "";
                $chunk_query = "INSERT OR IGNORE INTO [group_series_map]( id, series_id ) VALUES ";
                $chunk = 0;

                foreach ( $ids as $id_value )
                {
                    if ( $query != "" )
                    {
                        $query .= ",";
                    }
                    else
                    {
                        $query = $chunk_query;
                    }

                    $query .= "('" . $id . "','" . $id_value[ "id" ] . "')";

                    ++$chunk;
                    if ( $chunk >= 5000 )
                    {
                        $chunk = 0;
                        $results = $db->query( $query );
                        $query = "";
                    }
                }

                if ( $chunk > 0 )
                {
                    $results = $db->query( $query );
                }
            }

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 4 ) // Group remove.
        {
            $group_ids = [];
            $id_count = count( $ids );

            for ( $i = 0; $i < $id_count; ++$i )
            {
                $query = "WITH RECURSIVE under_root(id,level) AS ( VALUES(" . $ids[ $i ] . ",0)";
                $query .= " UNION ALL";
                $query .= " SELECT [series_group].id, under_root.level+1";
                $query .= " FROM [series_group] JOIN under_root ON [series_group].parent_id=under_root.id";
                $query .= " ORDER BY 1 ASC )";
                $query .= " SELECT id FROM under_root;";

                $results = $db->query( $query );

                while ( $row = $results->fetchArray() )
                {
                    array_push( $group_ids, $row[ "id" ] );
                }
            }

            $id_list = implode( ",", $group_ids );
            $query = "UPDATE [series] SET series_name_id=-1 WHERE series_name_id IN (" . $id_list . ")";
            $results = $db->exec( $query );
            $query = "UPDATE [series] SET season_name_id=-1 WHERE season_name_id IN (" . $id_list . ")";
            $results = $db->exec( $query );

            $query = "DELETE FROM [group_series_map] WHERE id IN (" . implode( ",", $group_ids ) . ")";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The update succeeded.
            //}

            $query = "DELETE FROM [series_group] WHERE id IN (" . implode( ",", $group_ids ) . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The update succeeded.
            }
        }
        else if ( $save_type == 5 ) // Content remove.
        {
            $query = "DELETE FROM [group_series_map] WHERE series_id IN (" . implode( ",", $ids ) . ")";
            $results = $db->exec( $query );

            //if ( $results && $db->changes() > 0 )
            //{
            //    $ret = 1;   // The delete succeeded.
            //}

            $query = "DELETE FROM [series] WHERE id IN (" . implode( ",", $ids ) . ")";
            $results = $db->exec( $query );

            if ( $results && $db->changes() > 0 )
            {
                $ret = 1;   // The delete succeeded.
            }
        }
        else if ( $save_type == 6 || $save_type == 7 )  // Save Group/Content Order.
        {
            $number_offset = 10000;
            $table_type = "series_group";
            if ( $save_type == 7 )
            {
                $number_offset = 1;
                $table_type = "series";
            }

            $query = "";
            $chunk_query = "UPDATE [" . $table_type . "] SET number= CASE ";
            $chunk = 0;

            $i = 0;
            $id_list = "";
            $id_count = count( $ids );
            for ( ; $i < $id_count; ++$i )
            {
                if ( $id_list != "" )
                {
                    $id_list .= ",";
                }

                $id_list .= $ids[ $i ];

                if ( $query == "" )
                {
                    $query = $chunk_query;
                }

                $query .= " WHEN id=" . $ids[ $i ] . " THEN " . $number_offset + $i;
                
                ++$chunk;
                if ( $chunk >= 5000 )
                {
                    $query .= " END WHERE id IN(" . $id_list . ")";
                    $id_list = "";

                    $chunk = 0;
                    $results = $db->query( $query );
                    $query = "";

                    if ( $results && $db->changes() > 0 )
                    {
                        $ret = 1;
                    }
                }
            }

            if ( $chunk > 0 )
            {
                $query .= " END WHERE id IN(" . $id_list . ")";

                $results = $db->query( $query );

                if ( $results && $db->changes() > 0 )
                {
                    $ret = 1;
                }
            }
        }
    }

    $db->close();
}

echo $ret;
?>
