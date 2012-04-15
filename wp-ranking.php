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

new WPRanking();

class WPRanking {

private $counter;
private $table = 'ranking_table';
private $nonce = 'wp-ranking';
private $action = 'wp-ranking-counter';
private $loader = 'wp-ranking-loader';
private $cookie = 'wp-ranking';

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
    $src = admin_url(sprintf(
        'admin-ajax.php?action=%s&id=%d',
        $this->loader,
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
        if (isset($_GET['id']) && intval($_GET['id'])) {
            if (isset($_COOKIE[$this->cookie]) && $_COOKIE[$this->cookie]) {
                global $wpdb;
                $sql = "REPLACE INTO `{$this->table}` VALUES(%s, %s, %s)";
                $sql = $wpdb->prepare(
                    $sql,
                    $_GET['id'],
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
        nocache_headers();
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
        $src = add_query_arg('id', intval($_GET['id']), $src);
        $src = add_query_arg('action', $this->action, $src);
        $src = add_query_arg('nonce', wp_create_nonce($this->nonce), $src);
        echo sprintf(
            file_get_contents(dirname(__FILE__).'/js/loader.js'),
            $src
        );
    }
    exit;
}

public function activation()
{
    global $wpdb;
    if ($wpdb->get_var("show tables like '$this->table'") != $this->table) {
        $sql = "CREATE TABLE `{$this->table}` (
            `id` bigint(20) unsigned not null,
            `session` varchar(32) not null,
            `datetime` bigint(20) not null,
            primary key (`id`, `session`),
            key `session` (`session`),
            key `datetime`(`datetime`)
            );";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

} // end WPRanking()


// EOF
