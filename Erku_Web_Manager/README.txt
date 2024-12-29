The Erku client requires the following files in order to run properly. It's Feed URL must point to the directory that contains these files.

get_content.php
get_content_info.php
get_epg.php
search.php
favorites.php

These files return JSON encoded strings. The format of each string is documented in each of those files. Refer to the $json_array variable and it's output.

get_content_info.php currently uses The Movie Database (TMDB) to retrieve content information. An API key is required and the $api_read_access_token variable needs to be filled out.
Set the $time_zone variable in get_epg.php to an appropriate value.

The /manager directory contains files that'll help process m3u/m3u8 playlists and XML based electronic programming guides (EPG). It can be accessed from your web browser.
The /databases directory contains the channels.db and vod.db SQLite databases where content is stored. It's also used to download the playlists and EPG.

Make sure the read and write permissions are set correctly on these directories so that all necessary files can be created and accessed.

The channels.db and vod.db databases have to be created first before any content is stored in them. Use the web manager to create them.
