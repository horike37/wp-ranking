<?php

new WPRankingShortcode();

class WPRankingShortcode {

private $cache_expire = 3600; // seconds

function __construct()
{
    add_shortcode('wp_ranking' , array(&$this, 'shortcode'));
}


public function shortcode($p)
{
    global $wpranking;
    $query_set = $wpranking->get_query_set();
    if (!isset($p['period']) || !isset($query_set[$p['period']])) {
        $p['period'] = apply_filters('wp_ranking_default_period', '30days');
    }
    if (!isset($p['rows']) || !intval($p['rows'])) {
        $p['rows'] = apply_filters('wp_ranking_default_rows', 5);
    }
    return $this->get_ranking($p['period'], $p['rows']);
}

public function get_ranking($query_set, $rows = 5)
{
    global $wpranking;
    $posts = $wpranking->get_ranking_data($query_set, $rows);
    $key = sprintf('wp_ranking_%s_%d', $query_set, $rows);
    if ($html = get_transient($key)) {
        return $html;
    } else {
        $list = array();
        $html = '<li class="post-%d"><a href="%s">%s</a></li>';
        foreach ($posts as $p) {
            $list[] = sprintf(
                $html,
                $p['post_id'],
                get_permalink($p['post_id']),
                get_the_title($p['post_id'])
            );
        }
        $html = sprintf(
            '<ol class="wp_ranking %s">%s</ol>',
            'wp_ranking_'.esc_attr($query_set),
            join('', $list)
        );
        set_transient(
            $key,
            $html,
            apply_filters('wp_ranking_cache_expire', $this->cache_expire)
        );
        return $html;
    }
}

} // end WPRankingShortCode()


// EOF
