<?php
/*
Plugin Name: WP Ranking
Author: Takayuki Miyauchi
Plugin URI: http://firegoby.jp/wp/wp-ranking
Description: WP Ranking Multisite Compatible.
Version: 0.5.1
Author URI: http://firegoby.jp/
Domain Path: /languages
Text Domain: wp-ranking
*/

require_once(dirname(__FILE__).'/includes/admin.class.php');

$wpranking = new WPRanking();

class WPRanking {

private $query_expire = 3600; // seconds
private $count_timer  = 4000; // msec
private $counter;
private $table   = 'ranking_table';
private $nonce   = 'wp-ranking';
private $action  = 'wp-ranking-counter';
private $loader  = 'wp-ranking-loader';
private $cookie  = 'wp-ranking';
private $dataset = array();

function __construct()
{
    global $wpdb;
    $this->table = mysql_real_escape_string($wpdb->base_prefix.$this->table);
    register_activation_hook(__FILE__, array(&$this, 'activation'));
    add_action('wp_ajax_nopriv_'.$this->action, array(&$this, 'counter'));
    add_action('wp_ajax_nopriv_'.$this->loader, array(&$this, 'loader'));
    add_action('wp_head', array(&$this, 'wp_head'));
    add_action('wp_footer', array(&$this, 'wp_footer'));
}

public function wp_head()
{
    if (!is_user_logged_in()) {
        wp_enqueue_script("jquery");
    }
}

public function wp_footer()
{
    if (is_user_logged_in() || !is_singular()) {
        return;
    }
    global $blog_id;
    $src = admin_url(sprintf(
        'admin-ajax.php?action=%s&blog_id=%d&post_id=%s',
        $this->loader,
        $blog_id,
        get_the_ID()
    ));
    printf(
        '<script type="text/javascript" src="%s"></script>',
        $src
    );
}

public function counter()
{
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    if (wp_verify_nonce($_GET['nonce'], $this->nonce) && !is_user_logged_in()) {
        if (isset($_GET['blog_id']) && intval($_GET['blog_id']) &&
                    isset($_GET['post_id']) && intval($_GET['post_id'])) {
            if (isset($_COOKIE[$this->cookie]) && $_COOKIE[$this->cookie]) {
                global $wpdb;
                $sql = "REPLACE INTO `{$this->table}` VALUES(%d, %d, %s, %d)";
                $sql = $wpdb->prepare(
                    $sql,
                    $_GET['blog_id'],
                    $_GET['post_id'],
                    $_COOKIE[$this->cookie],
                    time()
                );
                $wpdb->query($sql);
                echo json_encode(array('status' => true));
                exit;
            }
        }
    }
    header('HTTP', true, 403);
    echo json_encode(array('status' => false));
    exit;
}

public function loader()
{
    if (!is_user_logged_in()) {
        header('Content-type: text/javascript');
        if (!isset($_COOKIE[$this->cookie]) || !$_COOKIE[$this->cookie]) {
            $id = md5(uniqid(rand(),1));
            setcookie(
                $this->cookie,
                md5(uniqid(rand(),1)),
                time()+60*60*24
            );
        }
        $src = admin_url('admin-ajax.php');
        $src = add_query_arg('blog_id', intval($_GET['blog_id']), $src);
        $src = add_query_arg('post_id', intval($_GET['post_id']), $src);
        $src = add_query_arg('action', $this->action, $src);
        $src = add_query_arg('nonce', wp_create_nonce($this->nonce), $src);
        echo sprintf(
            file_get_contents(dirname(__FILE__).'/js/loader.js'),
            $src,
            $this->count_timer
        );
    }
    exit;
}

public function activation()
{
    global $wpdb;
    if ($wpdb->get_var("show tables like '$this->table'") != $this->table) {
        $sql = "CREATE TABLE `{$this->table}` (
            `blog_id` bigint(20) unsigned not null,
            `post_id` bigint(20) unsigned not null,
            `session` varchar(32) not null,
            `datetime` bigint(20) not null,
            primary key (`blog_id`, `post_id`, `session`),
            key `session` (`session`),
            key `datetime`(`datetime`)
            );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

public function get_ranking($dataset, $rows)
{
    global $wpdb;
    global $blog_id;

    $default_dataset = $this->get_dataset();
    $query = $default_dataset[$dataset];

    $sql = "select `post_id`, count(*) from `{$this->table}`";
    $sql .= " where `blog_id`=%d and `datetime` between %d and %d";
    $sql .= " group by `post_id`";
    $sql .= " order by count(*) desc";
    $sql .= " limit 0,%d";
    $sql = $wpdb->prepare(
        $sql,
        $blog_id,
        $query['start'],
        $query['end'],
        $rows
    );
    $key = 'wp-ranking-'.$dataset.'-'.$rows;
    $data = get_transient($key);
    if (!$data) {
        $data = $wpdb->query($sql, ARRAY_A);
        set_transient($key, $data, $this->query_expire);
    }

    return $data;
}

private function get_dataset()
{
    $this->dataset = array(
        array(
            'title' => __('Last 7 days', 'wp-ranking'),
            'start' => time()-60*60*24*7,
            'end'   => time()
        ),
        array(
            'title' => __('Last 30 days', 'wp-ranking'),
            'start' => time()-60*60*24*30,
            'end'   => time()
        ),
        array(
            'title' => __('Last week', 'wp-ranking'),
            'start' => strtotime("sunday previous week")-60*60*24*7,
            'end'   => strtotime("sunday previous week")
        ),
        array(
            'title' => __('Last month', 'wp-ranking'),
            'start' => strtotime("first day of previous month"),
            'end'   => strtotime(date('Y-m-d', strtotime("first day of this month")))
        ),
    );

    return apply_filters('wp-ranking-dataset', $this->dataset);
}

} // end WPRanking()


// EOF
