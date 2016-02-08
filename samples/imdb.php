<?php

// Sample TASK: get past-2015 movies from IMDB Top 250 and write to MySQL DB. Take cover images as well
// ----------------------------------------------------------------------------------------------------

require "../parsemx.php";

begin_debug();

$q_database = "imdb_sample";
// By default, localhost root/root MySQL user is used. Set $q_server, $q_user, $q_password to override

q("CREATE TABLE IF NOT EXISTS films (title VARCHAR(250), year SMALLINT, cover VARCHAR(250))");

http_get('http://www.imdb.com/chart/top/');

foreach (tags_html('.titleColumn') as $film) {
    set_source($film);
    $year = inside('(', ')', tag_text('.secondaryInfo'));
    if ($year < 2015) continue;

    http_get(tag_link('a')); // Open film link
    $title = q_escape(replace('(*)', '', tag_text('h1'))); // Take title and remove year like (2015) from it
    if (q("SELECT * FROM films WHERE title=$title")) continue; // If film already in DB, skip

    http_get(tag_link('.poster')); // Open poster link
    $cover = q_escape(http_get_file(tag_image('#primary-img'), 'covers/')); // Download primary image from slideshow
    q("INSERT INTO films SET title=$title, year=$year, cover=$cover");
}

// Lets output the films from our DB
foreach (qq("SELECT * FROM films") as $film)
    echo "<h1><img src='$film[cover]' /> $film[title] <i>($film[year])</i></h1>";