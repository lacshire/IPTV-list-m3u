<?php
$type = isset( $_REQUEST[ "type" ] ) ? ( int )$_REQUEST[ "type" ] : -1;
$name = isset( $_REQUEST[ "name" ] ) ? $_REQUEST[ "name" ] : "";
$year = isset( $_REQUEST[ "year" ] ) ? ( int )$_REQUEST[ "year" ] : 0;

$season = isset( $_REQUEST[ "season" ] ) && $_REQUEST[ "season" ] != "" ? ( int )$_REQUEST[ "season" ] : -1;
$episode = isset( $_REQUEST[ "episode" ] ) && $_REQUEST[ "episode" ] != "" ? ( int )$_REQUEST[ "episode" ] : -1;

$api_read_access_token = "";

$authorization = array( "Authorization: Bearer " . $api_read_access_token,
                        "Accept: application/json" );

$json_array = [ "data" => array( "title" => "",
                                 "runtime" => 0,
                                 "rating" => "",
                                 "release_date" => "",
                                 "end_date" => "",
                                 "seasons" => 0,
                                 "episodes" => 0,
                                 "poster_url" => "",
                                 "description" => "",
                                 "genres" => [],
                                 "directors" => [],
                                 "actors" => [] ) ];

//$langauge = "en";         // ISO-639-1
$region = "US";             // ISO-3166-1
$language_tag = "en-US";    // ISO-639-1 and ISO-3166-1

/*
http://image.tmdb.org/t/p/
"poster_sizes": [
      "w92",
      "w154",
      "w185",
      "w342",
      "w500",
      "w780",
      "original"
    ]
*/

function SAFE_STR( $str )
{
    return ( is_null( $str ) ? "" : $str );
}

function GetData( $url )
{
    global $authorization;

    $ch = curl_init();

    curl_setopt( $ch, CURLOPT_HTTPHEADER, $authorization );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
    curl_setopt( $ch, CURLOPT_URL, $url );
    curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );

    $data = curl_exec( $ch );

    curl_close( $ch );

    return json_decode( $data, true );
}

if ( $type == 1 )   // Movies
{
    $arr = GetData( "https://api.themoviedb.org/3/search/movie?query=" . urlencode( $name ) . "&include_adult=true&language=" . $language_tag . "&page=1&year=" . $year );

    $result_count = count( $arr[ "results" ] );
    if ( $result_count == 0 )   // Try the search without punctuation.
    {
        $name = preg_replace( "#[[:punct:]]#", "", $name );
        $arr = GetData( "https://api.themoviedb.org/3/search/movie?query=" . urlencode( $name ) . "&include_adult=true&language=" . $language_tag . "&page=1&year=" . $year );

        $result_count = count( $arr[ "results" ] );
    }

    if ( $result_count > 0 )
    {
        $id = $arr[ "results" ][ 0 ][ "id" ];

        $arr = GetData( "https://api.themoviedb.org/3/movie/" . $id . "?append_to_response=credits,release_dates&language=" . $language_tag );

        $json_array[ "data" ][ "title" ] = SAFE_STR( $arr[ "title" ] );

        $json_array[ "data" ][ "runtime" ] = ( int )$arr[ "runtime" ];
        if ( !is_null( $arr[ "poster_path" ] ) )
        {
            $json_array[ "data" ][ "poster_url" ] = "http://image.tmdb.org/t/p/w342" . $arr[ "poster_path" ];
        }
        $json_array[ "data" ][ "description" ] = SAFE_STR( $arr[ "overview" ] );
        $json_array[ "data" ][ "release_date" ] = SAFE_STR( $arr[ "release_date" ] );

        foreach ( $arr[ "genres" ] as $genre )
        {
            if ( !is_null( $genre ) )
            {
                array_push( $json_array[ "data" ][ "genres" ], $genre[ "name" ] );
            }
        }

        foreach ( $arr[ "credits" ][ "cast" ] as $cast )
        {
            $actor = array( "name" => "", "character_name" => "", "photo_url" => "" );

            $actor[ "name" ] = SAFE_STR( $cast[ "name" ] );
            $actor[ "character_name" ] = SAFE_STR( $cast[ "character" ] );
            if ( !is_null( $cast[ "profile_path" ] ) )
            {
                $actor[ "photo_url" ] = "http://image.tmdb.org/t/p/w154" . $cast[ "profile_path" ];
            }

            array_push( $json_array[ "data" ][ "actors" ], $actor );
        }

        foreach ( $arr[ "credits" ][ "crew" ] as $crew )
        {
            if ( !is_null( $crew[ "job" ] ) && strcmp( $crew[ "job" ], "Director" ) == 0 )
            {
                if ( !is_null( $crew[ "name" ] ) )
                {
                    array_push( $json_array[ "data" ][ "directors" ], $crew[ "name" ] );
                }
            }
        }

        foreach ( $arr[ "release_dates" ][ "results" ] as $release )
        {
            if ( !is_null( $release[ "iso_3166_1" ] ) && strcmp( $release[ "iso_3166_1" ], $region ) == 0 )
            {
                if ( count( $release[ "release_dates" ] ) > 0 )
                {
                    $json_array[ "data" ][ "rating" ] = SAFE_STR( $release[ "release_dates" ][ 0 ][ "certification" ] );
                }

                break;
            }
        }
    }
}
else if ( $type == 2 )   // TV Shows
{
    $arr = GetData( "https://api.themoviedb.org/3/search/tv?query=" . urlencode( $name ) . "&include_adult=true&language=" . $language_tag . "&page=1&year=" . $year );

    $result_count = count( $arr[ "results" ] );
    if ( $result_count == 0 )   // Try the search without punctuation.
    {
        $name = preg_replace( "#[[:punct:]]#", "", $name );
        $arr = GetData( "https://api.themoviedb.org/3/search/tv?query=" . urlencode( $name ) . "&include_adult=true&language=" . $language_tag . "&page=1&year=" . $year );

        $result_count = count( $arr[ "results" ] );
    }

    if ( $result_count > 0 )
    {
        $id = $arr[ "results" ][ 0 ][ "id" ];

        if ( $season == -1 )    // Get info on the TV Show.
        {
            $arr = GetData( "https://api.themoviedb.org/3/tv/" . $id . "?append_to_response=aggregate_credits&language=" . $language_tag );

            $json_array[ "data" ][ "title" ] = SAFE_STR( $arr[ "name" ] );

            $json_array[ "data" ][ "seasons" ] = ( int )$arr[ "number_of_seasons" ];
            $json_array[ "data" ][ "episodes" ] = ( int )$arr[ "number_of_episodes" ];
            if ( !is_null( $arr[ "poster_path" ] ) )
            {
                $json_array[ "data" ][ "poster_url" ] = "http://image.tmdb.org/t/p/w342" . $arr[ "poster_path" ];
            }
            $json_array[ "data" ][ "description" ] = SAFE_STR( $arr[ "overview" ] );
            $json_array[ "data" ][ "release_date" ] = SAFE_STR( $arr[ "first_air_date" ] );
            $json_array[ "data" ][ "end_date" ] = SAFE_STR( $arr[ "last_air_date" ] );

            foreach ( $arr[ "genres" ] as $genre )
            {
                if ( !is_null( $genre ) )
                {
                    array_push( $json_array[ "data" ][ "genres" ], $genre[ "name" ] );
                }
            }

            $cast_limit = 0;
            foreach ( $arr[ "aggregate_credits" ][ "cast" ] as $cast )
            {
                $actor = array( "name" => "", "character_name" => "", "photo_url" => "" );

                $actor[ "name" ] = SAFE_STR( $cast[ "name" ] );
                $role_count = count( $cast[ "roles" ] );
                if ( $role_count > 0 )
                {
                    $actor[ "character_name" ] = SAFE_STR( $cast[ "roles" ][ 0 ][ "character" ] );

                    if ( $role_count > 1 )
                    {
                        $actor[ "character_name" ] .= " and others";
                    }
                }
                if ( !is_null( $cast[ "profile_path" ] ) )
                {
                    $actor[ "photo_url" ] = "http://image.tmdb.org/t/p/w154" . $cast[ "profile_path" ];
                }

                array_push( $json_array[ "data" ][ "actors" ], $actor );

                if ( ++$cast_limit == 100 )
                {
                    break;
                }
            }
        }
        else if ( $season != -1 && $episode == -1 ) // Get info on the Season.
        {
            $arr = GetData( "https://api.themoviedb.org/3/tv/" . $id . "/season/" . $season . "?append_to_response=aggregate_credits&language=" . $language_tag );

            $json_array[ "data" ][ "title" ] = SAFE_STR( $arr[ "name" ] );

            $episode_count = 0;
            $runtime = 0;
            foreach ( $arr[ "episodes" ] as $episode )
            {
                ++$episode_count;
                $runtime += $episode[ "runtime" ];
            }
            $json_array[ "data" ][ "episodes" ] = $episode_count;
            $json_array[ "data" ][ "runtime" ] = $runtime;
            if ( !is_null( $arr[ "poster_path" ] ) )
            {
                $json_array[ "data" ][ "poster_url" ] = "http://image.tmdb.org/t/p/w342" . $arr[ "poster_path" ];
            }
            $json_array[ "data" ][ "description" ] = SAFE_STR( $arr[ "overview" ] );
            $json_array[ "data" ][ "release_date" ] = SAFE_STR( $arr[ "air_date" ] );

            $cast_limit = 0;
            foreach ( $arr[ "aggregate_credits" ][ "cast" ] as $cast )
            {
                $actor = array( "name" => "", "character_name" => "", "photo_url" => "" );

                $actor[ "name" ] = SAFE_STR( $cast[ "name" ] );
                $role_count = count( $cast[ "roles" ] );
                if ( $role_count > 0 )
                {
                    $actor[ "character_name" ] = SAFE_STR( $cast[ "roles" ][ 0 ][ "character" ] );

                    if ( $role_count > 1 )
                    {
                        $actor[ "character_name" ] .= " and others";
                    }
                }
                if ( !is_null( $cast[ "profile_path" ] ) )
                {
                    $actor[ "photo_url" ] = "http://image.tmdb.org/t/p/w154" . $cast[ "profile_path" ];
                }

                array_push( $json_array[ "data" ][ "actors" ], $actor );

                if ( ++$cast_limit == 100 )
                {
                    break;
                }
            }
        }
        else if ( $season != -1 && $episode != -1 ) // Get info on the Episode.
        {
            $arr = GetData( "https://api.themoviedb.org/3/tv/" . $id . "/season/" . $season . "/episode/" . $episode . "?append_to_response=credits&language=" . $language_tag );

            $json_array[ "data" ][ "title" ] = SAFE_STR( $arr[ "name" ] );

            $json_array[ "data" ][ "runtime" ] = ( int )$arr[ "runtime" ];
            if ( !is_null( $arr[ "still_path" ] ) )
            {
                $json_array[ "data" ][ "poster_url" ] = "http://image.tmdb.org/t/p/w342" . $arr[ "still_path" ];
            }
            $json_array[ "data" ][ "description" ] = SAFE_STR( $arr[ "overview" ] );
            $json_array[ "data" ][ "release_date" ] = SAFE_STR( $arr[ "air_date" ] );

            foreach ( $arr[ "credits" ][ "cast" ] as $cast )
            {
                $actor = array( "name" => "", "character_name" => "", "photo_url" => "" );

                $actor[ "name" ] = SAFE_STR( $cast[ "name" ] );
                $actor[ "character_name" ] = SAFE_STR( $cast[ "character" ] );
                if ( !is_null( $cast[ "profile_path" ] ) )
                {
                    $actor[ "photo_url" ] = "http://image.tmdb.org/t/p/w154" . $cast[ "profile_path" ];
                }

                array_push( $json_array[ "data" ][ "actors" ], $actor );
            }

            foreach ( $arr[ "credits" ][ "guest_stars" ] as $guest_star )
            {
                $actor = array( "name" => "", "character_name" => "", "photo_url" => "" );

                $actor[ "name" ] = SAFE_STR( $guest_star[ "name" ] );
                $actor[ "character_name" ] = SAFE_STR( $guest_star[ "character" ] );
                if ( !is_null( $guest_star[ "profile_path" ] ) )
                {
                    $actor[ "photo_url" ] = "http://image.tmdb.org/t/p/w154" . $guest_star[ "profile_path" ];
                }

                array_push( $json_array[ "data" ][ "actors" ], $actor );
            }

            foreach ( $arr[ "credits" ][ "crew" ] as $crew )
            {
                if ( !is_null( $crew[ "job" ] ) && strcmp( $crew[ "job" ], "Director" ) == 0 )
                {
                    if ( !is_null( $crew[ "name" ] ) )
                    {
                        array_push( $json_array[ "data" ][ "directors" ], $crew[ "name" ] );
                    }
                }
            }
        }
    }
}

header( "Access-Control-Allow-Origin: *" );
echo json_encode( $json_array, JSON_UNESCAPED_SLASHES );
?>
