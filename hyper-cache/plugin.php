<?php

/*
  Plugin Name: Hyper Cache For SAE
  Plugin URI: http://www.satollo.net/plugins/hyper-cache
  Description: A easy to configure and efficient cache to increase the speed of your blog.
  Version: 3.1.9
  Author: Stefano Lissa & Xider
  Author URI: http://www.satollo.net
  Disclaimer: Use at your own risk. No warranty expressed or implied is provided.
  Contributors: satollo
 */

if (!defined('HYPER_CACHE_LOG')) {
    define('HYPER_CACHE_LOG', false);
}

if (isset($_GET['cache'])) {
    if ($_GET['cache'] === '0') {
        setcookie('cache_disable', 1, time() + 3600 * 24 * 365, '/');
        $x = strpos($_SERVER['REQUEST_URI'], '?');
        header('Location:' . substr($_SERVER['REQUEST_URI'], 0, $x));
        die();
    }

    if ($_GET['cache'] === '1') {
        setcookie('cache_disable', 1, time() - 3600, '/');
        $x = strpos($_SERVER['REQUEST_URI'], '?');
        header('Location:' . substr($_SERVER['REQUEST_URI'], 0, $x));
        die();
    }
}

new HyperCache();

global $cache_stop;

class HyperCache {

    var $post_id;
    var $options;
    var $ob_started = false;
    static $instance;

    const MOBILE_AGENTS = 'android|iphone|iemobile|up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|ipod|xoom|blackberry';

    function __construct() {
        self::$instance = $this;
        $this->options = get_option('hyper-cache', array());

        register_activation_hook('hyper-cache/plugin.php', array($this, 'hook_activate'));
        register_deactivation_hook('hyper-cache/plugin.php', array($this, 'hook_deactivate'));

        add_action('edit_post', array($this, 'hook_edit_post'), 1);
        add_action('save_post', array($this, 'hook_save_post'), 1);
        add_action('comment_post', array($this, 'hook_comment_post'), 1, 2);
        add_action('wp_update_comment_count', array($this, 'hook_wp_update_comment_count'), 1);
        add_action('bbp_new_reply', array($this, 'hook_bbp_new_reply'));
        add_action('bbp_new_topic', array($this, 'hook_bbp_new_topic'));

        add_action('hyper_cache_clean', array($this, 'hook_hyper_cache_clean'));

        if (!is_admin() && !isset($_COOKIE['cache_disable'])) {

            // The function must exists or the advanced-cache.php has been removed
            global $hyper_cache_is_mobile;
            if ($hyper_cache_is_mobile && !empty($this->options['theme'])) {
                add_filter('stylesheet', array($this, 'hook_get_stylesheet'));
                add_filter('template', array($this, 'hook_get_template'));
            }
            add_action('template_redirect', array($this, 'hook_template_redirect'), 0);
        }

        if (is_admin()) {
            add_action('admin_init', array($this, 'hook_admin_init'));
            add_action('admin_head', array($this, 'hook_admin_head'));
            add_action('admin_menu', array($this, 'hook_admin_menu'));

            if (!is_dir(WP_CONTENT_DIR . '/logs')) {
                wp_mkdir_p(WP_CONTENT_DIR . '/logs');
            }
        }
    }

    function log($object) {
        if (is_object($object) || is_array($object)) {
            $object = print_r($object, true);
        }
        //file_put_contents(WP_CONTENT_DIR . '/logs/hyper-cache.log', $object, FILE_APPEND);
        error_log($object);
    }

    function hook_activate() {
        /*if (!is_dir(WP_CONTENT_DIR . '/logs')) {
            wp_mkdir_p(WP_CONTENT_DIR . '/logs');
        }*/

        if (!isset($this->options['mobile'])) {
            $this->options['mobile'] = 0;
        }
        if (!isset($this->options['folder'])) {
            $this->options['folder'] = '';
        }
        if (!isset($this->options['max_age'])) {
            $this->options['max_age'] = 24;
        }
        if (!isset($this->options['clean_last_posts'])) {
            $this->options['clean_last_posts'] = 0;
        }
        if (!isset($this->options['mobile_agents'])) {
            $this->options['mobile_agents'] = explode('|', self::MOBILE_AGENTS);
        }
        if (!isset($this->options['reject_agents']))
            $this->options['reject_agents'] = array();
        if (!isset($this->options['reject_cookies']))
            $this->options['reject_cookies'] = array();
        if (!isset($this->options['reject_uris']))
            $this->options['reject_uris'] = array();
        if (!isset($this->options['reject_uris_exact']))
            $this->options['reject_uris_exact'] = array();
        if (!isset($this->options['clean_last_posts']))
            $this->options['clean_last_posts'] = 0;

        if (!isset($this->options['https']))
            $this->options['https'] = 1;

        if (!isset($this->options['theme']))
            $this->options['theme'] = '';

        if (!isset($this->options['browser_cache_hours']))
            $this->options['browser_cache_hours'] = 24;

        update_option('hyper-cache', $this->options);

        //@wp_mkdir_p(WP_CONTENT_DIR . '/cache/hyper-cache');

       /* if (is_file(WP_CONTENT_DIR . '/advanced-cache.php')) {
            $this->build_advanced_cache();
            touch(WP_CONTENT_DIR . '/advanced-cache.php');
        }*/

        if (!wp_next_scheduled('hyper_cache_clean')) {
            wp_schedule_event(time() + 300, 'hourly', 'hyper_cache_clean');
        }
    }

    function hook_deactivate() {
        // Reset the file without deleting it to avoid to lost manually assigned permissions
        //file_put_contents(WP_CONTENT_DIR . '/advanced-cache.php', '');
        wp_clear_scheduled_hook('hyper_cache_clean');
    }

    function hook_admin_init() {
        if (isset($_GET['page']) && strpos($_GET['page'], 'hyper-cache/') === 0) {
            wp_enqueue_script('jquery-ui-tabs');
        }
    }

    function hook_admin_head() {
        if (isset($_GET['page']) && strpos($_GET['page'], 'hyper-cache/') === 0) {
            echo '<link type="text/css" rel="stylesheet" href="' . plugins_url('hyper-cache') . '/admin.css"/>';
        }
    }

    function hook_admin_menu() {
        add_options_page('Hyper Cache', 'Hyper Cache', 'manage_options', 'hyper-cache/options.php');
    }

    function build_advanced_cache() {
        $advanced_cache = file_get_contents(dirname(__FILE__) . '/advanced-cache.php');
        $advanced_cache = str_replace('HC_MOBILE_AGENTS', implode('|', array_map('preg_quote', $this->options['mobile_agents'])), $advanced_cache);
        $advanced_cache = str_replace('HC_MOBILE', $this->options['mobile'], $advanced_cache);

        $advanced_cache = str_replace('HC_REJECT_AGENTS_ENABLED', empty($this->options['reject_agents_enabled']) ? 0 : 1, $advanced_cache);
        $advanced_cache = str_replace('HC_REJECT_AGENTS', implode('|', array_map('preg_quote', $this->options['reject_agents'])), $advanced_cache);

        $advanced_cache = str_replace('HC_REJECT_COOKIES_ENABLED', empty($this->options['reject_cookies_enabled']) ? 0 : 1, $advanced_cache);
        $advanced_cache = str_replace('HC_REJECT_COOKIES', implode('|', array_map('preg_quote', $this->options['reject_cookies'])), $advanced_cache);


        $advanced_cache = str_replace('HC_GZIP', isset($this->options['gzip']) ? 1 : 0, $advanced_cache);
        $advanced_cache = str_replace('HC_FOLDER', $this->get_folder(), $advanced_cache);
        $advanced_cache = str_replace('HC_MAX_AGE', $this->options['max_age'], $advanced_cache);
        $advanced_cache = str_replace('HC_REJECT_COMMENT_AUTHORS', isset($this->options['reject_comment_authors']) ? 1 : 0, $advanced_cache);

        $advanced_cache = str_replace('HC_BROWSER_CACHE_HOURS', $this->options['browser_cache_hours'], $advanced_cache);
        $advanced_cache = str_replace('HC_BROWSER_CACHE', isset($this->options['browser_cache']) ? 1 : 0, $advanced_cache);

        $advanced_cache = str_replace('HC_HTTPS', (int) $this->options['https'], $advanced_cache);
        $advanced_cache = str_replace('HC_READFILE', isset($this->options['readfile']) ? 1 : 0, $advanced_cache);

        $advanced_cache = str_replace('HC_SERVE_EXPIRED_TO_BOT', isset($this->options['serve_expired_to_bots']) ? 1 : 0, $advanced_cache);
        $advanced_cache = str_replace('HC_BOTS_IGNORE_NOCACHE', isset($this->options['bots_ignore_nocache']) ? 1 : 0, $advanced_cache);

        //return file_put_contents(WP_CONTENT_DIR . '/advanced-cache.php', $advanced_cache);
        return 1;
    }

    function hook_bbp_new_reply($reply_id) {
        $topic_id = bbp_get_reply_topic_id($reply_id);
        $topic_url = bbp_get_topic_permalink($topic_id);
        //$dir = $this->get_folder() . '' . substr($topic_url, strlen(get_option('home'))) . '/';
        $dir =substr($topic_url, strpos($topic_url, '://') + 3) . '/';
        //$this->remove_dir($dir);
        $this->deletekvdb($dir);

        $forum_id = bbp_get_reply_forum_id($reply_id);
        $forum_url = bbp_get_forum_permalink($forum_id);
        //$dir = $this->get_folder() . '' . substr($forum_url, strlen(get_option('home'))) . '/';
        $dir =substr($topic_url, strpos($forum_url, '://') + 3) . '/';
        //$this->remove_dir($dir);
        $this->deletekvdb($dir);
    }

    function hook_bbp_new_topic($topic_id) {
        $topic_url = bbp_get_topic_permalink($topic_id);
        //$dir = $this->get_folder() . '' . substr($topic_url, strlen(get_option('home'))) . '/';
        $dir = substr($topic_url, strpos($topic_url, '://') + 3) . '/';
        //$this->remove_dir($dir);
        $this->deletekvdb($dir);

        $forum_id = bbp_get_topic_forum_id($topic_id);
        $forum_url = bbp_get_forum_permalink($forum_id);
        $dir = substr($topic_url, strpos($forum_url, '://') + 3) . '/';
        //$dir = $this->get_folder() . '' . substr($forum_url, strlen(get_option('home'))) . '/';
        //$this->remove_dir($dir);
        $this->deletekvdb($dir);
    }

    function hook_comment_post($comment_id, $status) {
        if (HYPER_CACHE_LOG)
            $this->log('Hook: comment_post');
        if ($status === 1) {
            $comment = get_comment($comment_id);
            $this->clean_post($comment->comment_post_ID, isset($this->options['clean_archives_on_comment']), isset($this->options['clean_archives_on_comment']));
        }
    }

    function hook_wp_update_comment_count($post_id) {
        if (HYPER_CACHE_LOG)
            $this->log('Hook: wp_update_comment_count');
        if ($this->post_id == $post_id) {
            return;
        }
        $this->clean_post($post_id, isset($this->options['clean_archives_on_comment']), isset($this->options['clean_home_on_comment']));
    }

    function hook_save_post($post_id) {
        if (HYPER_CACHE_LOG)
            $this->log('Hook: save_post');
    }

    /**
     * edit_post is called even when a comment is added, but the comment hook prevent the execution of
     * edit_post like if the post has been modified.
     */
    function hook_edit_post($post_id) {
        if (HYPER_CACHE_LOG)
            $this->log('Hook: edit_post');
        $this->clean_post($post_id, isset($this->options['clean_archives_on_post_edit']), isset($this->options['clean_home_on_post_edit']));
    }

    function clean_post($post_id, $clean_archives = true, $clean_home = true) {
        if (HYPER_CACHE_LOG)
            $this->log('Cleaning post ' . $post_id);
        // When someone deletes the advaced-cache.php file
        if (!function_exists('hyper_cache_sanitize_uri')) {
            if (HYPER_CACHE_LOG)
                $this->log('hyper_cache_sanitize_uri does not exists');
            return;
        }

        if ($this->post_id == $post_id) {
            if (HYPER_CACHE_LOG)
                $this->log('Already cleaned in this session');
            return;
        }

        $status = get_post_status($post_id);
        if (HYPER_CACHE_LOG)
            $this->log('Status: ' . $status);
        if ($status != 'publish' && $status != 'trash') {
            if (HYPER_CACHE_LOG)
                $this->log('Post not published');
            //$this->log('Not a published post');
            return;
        }

        if ($status == 'trash') {
            $clean_archives = true;
            $clean_home = true;
        }

        $this->post_id = $post_id;
        //$folder = trailingslashit($this->get_folder());
        $dir = $this->post_folder($post_id);
        //$this->remove_dir($dir);
        $this->deletekvdb($dir);

        if ($this->options['clean_last_posts'] != 0) {
            $posts = get_posts(array('numberposts' => $this->options['clean_last_posts']));
            foreach ($posts as &$post) {
                $dir = $this->post_folder($post_id);
                //$this->remove_dir($dir);
                $this->deletekvdb($dir);
            }
        }


        $dir = $folder . substr(get_option('home'), strpos(get_option('home'), '://') + 3);

        if ($clean_home) {
            if (HYPER_CACHE_LOG)
                $this->log('Cleaning the home');

            // The home
            if (HYPER_CACHE_LOG)
                $this->log('Cleaning the home index*');
            /*
            @unlink($dir . '/index.html');
            @unlink($dir . '/index.html.gz');
            @unlink($dir . '/index-https.html');
            @unlink($dir . '/index-https.html.gz');
            @unlink($dir . '/index-mobile.html');
            @unlink($dir . '/index-mobile.html.gz');
            @unlink($dir . '/index-https-mobile.html');
            @unlink($dir . '/index-https-mobile.html.gz');*/
            $this->deletekvdb('index');

            //$this->remove_dir($dir . '/feed/');
            $this->deletekvdb('feed');
            // Home subpages
            //$this->remove_dir($dir . '/page/');
            $this->deletekvdb('page');
        }

        //@unlink($dir . '/robots.txt');
        if ($clean_archives) {
            if (HYPER_CACHE_LOG)
                $this->log('Cleaning archives');

            $base = get_option('category_base');
            if (empty($base)) {
                $base = 'category';
            }
            //$this->remove_dir($dir . '/' . $base . '/');
            $this->deletekvdb($base);

            $permalink_structure = get_option('permalink_structure');
            //$this->log(substr($permalink_structure, 0, 11));
            if (substr($permalink_structure, 0, 11) == '/%category%') {
                $categories = get_categories();
                //$this->log(print_r($categories, true));
                foreach ($categories as &$category) {
                    //$this->log('Removing: ' . $dir . '/' . $category->slug . '/');
                    //$this->remove_page($dir . '/' . $category->slug);
                    $this->deletekvdb($category->slug);
                }
            }

            $base = get_option('tag_base');
            if (empty($base)) {
                $base = 'tag';
            }
            //$this->remove_dir($dir . '/' . $base . '/');
            $this->deletekvdb($base);

            //$this->remove_dir($dir . '/type/');
            $this->deletekvdb('type');

            //$this->remove_dir($dir . '/' . date('Y') . '/');
            $this->deletekvdb(date('Y'));
        }
    }

    /*
     * Runs only if $hyper_cache_is_mobile is true
     */

    function hook_get_stylesheet($stylesheet = '') {
        $theme = wp_get_theme($this->options['theme']);
        if (!$theme->exists()) {
            return $stylesheet;
        }
        return $theme->stylesheet;
    }

    /*
     * Runs only if $hyper_cache_is_mobile is true
     *
     * var WP_Theme $theme
     */

    function hook_get_template($template) {
        $theme = wp_get_theme($this->options['theme']);
        if (!$theme->exists()) {
            return $template;
        }
        return $theme->template;
    }

    function hook_template_redirect() {
        global $cache_stop, $hyper_cache_stop, $lite_cache_stop;

        if (HYPER_CACHE_LOG) {
            $this->log('hook_template_redirect');
        }

        if ($this->ob_started) {
            return;
        }

        $home_root = parse_url(get_option('home'), PHP_URL_PATH);

        if ($cache_stop || $hyper_cache_stop || $lite_cache_stop) {

        } else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $cache_stop = true;
        } else if (!empty($_SERVER['QUERY_STRING'])) {
            $cache_stop = true;
        } else if (is_user_logged_in()) {
            $cache_stop = true;
        } else if (is_trackback()) {
            $cache_stop = true;
        }

        // Global feed and global comment feed
        else if (isset($this->options['reject_feeds']) && is_feed()) {
            $cache_stop = true;
        }

        // Single post/page feed
        else if (isset($this->options['reject_comment_feeds']) && is_comment_feed()) {
            $cache_stop = true;
        } else if (isset($this->options['reject_home']) && is_front_page()) {
            $cache_stop = true;
        } else if (is_robots()) {
            $cache_stop = true;
        }

        if (defined('SID') && SID != '') {
            $cache_stop = true;
        }

        // Compatibility with XML Sitemap 4.x
        else if (substr($_SERVER['REQUEST_URI'], 0, strlen($home_root) + 8) == ($home_root . '/sitemap')) {
            $cache_stop = true;
        }
        // Never cache pages generated for administrator (to be patched to see if the user is an administrator)
        //if (get_current_user_id() == 1) return;
        else if (is_404()) {
            if (isset($this->options['reject_404'])) {
                $cache_stop = true;
            } else {
                $file = $this->get_folder() . '/' . substr(get_option('home'), strpos(get_option('home'), '://') + 3) . '/404.html';

                if (file_exists($file) && ($this->options['max_age'] == 0 || filemtime($file) > time() - $this->options['max_age'] * 3600)) {
                    header('Content-Type: text/html;charset=UTF-8');
                    // For some reason it seems more performant than readfile...
                    header('X-Hyper-Cache: hit,404');
                    echo file_get_contents($file);
                    die();
                }
            }
        }

        if (!$cache_stop && substr($_SERVER['REQUEST_URI'], 0, strlen($home_root) + 4) == ($home_root . '/wp-')) {
            $cache_stop = true;
        }



        // URLs to reject (exact)
        if (!$cache_stop && isset($this->options['reject_uris_exact_enabled'])) {
            if (is_array($this->options['reject_uris_exact'])) {
                foreach ($this->options['reject_uris_exact'] as &$uri) {
                    if ($_SERVER['REQUEST_URI'] == $uri) {
                        $cache_stop = true;
                        break;
                    }
                }
            }
        }

        // URLs to reject
        if (!$cache_stop && isset($this->options['reject_uris_enabled'])) {
            if (is_array($this->options['reject_uris'])) {
                foreach ($this->options['reject_uris'] as &$uri) {
                    if (strpos($_SERVER['REQUEST_URI'], $uri) === 0) {
                        $cache_stop = true;
                        break;
                    }
                }
            }
        }

        if (!$cache_stop && !empty($this->options['reject_old_posts']) && is_single()) {
            global $post;
            if (strtotime($post->post_date_gmt) < time() - 86400 * $this->options['reject_old_posts'])
                return;
        }

        // If is not require to bypass the comment authors, remove the cookies so the page is generated without
        // comment moderation noticies
        if (!isset($this->options['reject_comment_authors'])) {
            foreach ($_COOKIE as $n => $v) {
                if (substr($n, 0, 14) == 'comment_author') {
                    unset($_COOKIE[$n]);
                }
            }
        }

        $this->ob_started = true;
        ob_start('hyper_cache_callback');
    }

    function post_folder($post_id) {
        $url = get_permalink($post_id);
        $parts = parse_url($url);
        return $parts['host'] . hyper_cache_sanitize_uri($parts['path']);
    }
/*
    function remove_page($dir) {
        if (HYPER_CACHE_LOG)
            $this->log('Removing page: ' . $dir);
        $dir = untrailingslashit($dir);
        @unlink($dir . '/index.html');
        @unlink($dir . '/index.html.gz');
        @unlink($dir . '/index-https.html');
        @unlink($dir . '/index-https.html.gz');
        @unlink($dir . '/index-mobile.html');
        @unlink($dir . '/index-mobile.html.gz');
        @unlink($dir . '/index-https-mobile.html');
        @unlink($dir . '/index-https-mobile.html.gz');

        //$this->remove_dir($dir . '/feed/');
        $this->deletekvdb('feed');
        // Pagination
        //$this->remove_dir($dir . '/page/');
        $this->deletekvdb('page');
    }
*/

/**
*
*    Hyper Cache For SAE Start
*
*/
function deletekvdb($name='',$is_file=false){
    $kv = new SaeKV();
// 初始化SaeKV对象
$ret = $kv->init();
if($is_file){
$ret = $kv->delete('cache/'.strtolower($_SERVER['HTTP_HOST']).'/'.$name);
}else{
    $ret = $kv->pkrget('cache/'.strtolower($_SERVER['HTTP_HOST']).'/'.$name, 100);
while (true) {
   foreach($ret as $k=>$v)
    $kv->delete($k);
   end($ret);
   $start_key = key($ret);
   $i = count($ret);
   if ($i < 100) break;
   $ret = $kv->pkrget('cache/'.strtolower($_SERVER['HTTP_HOST']).$name, 100, $start_key);
}
}

}
/*
    function remove_dir($dir) {
        if (HYPER_CACHE_LOG) {
            $this->log('Removing dir: ' . $dir);
        }
        $dir = trailingslashit($dir);
        
        $files = glob($dir . '*', GLOB_MARK);
        if (!empty($files)) {
            foreach ($files as &$file) {
                if (substr($file, -1) == DIRECTORY_SEPARATOR)
                    $this->remove_dir($file);
                else {
                    @unlink($file);
                }
            }
        }
        @rmdir($dir);
    }*/

    function hook_hyper_cache_clean() {
        if (!isset($this->options['autoclean'])) {
            return;
        }
        if (HYPER_CACHE_LOG) {
            $this->log('Periodic cache cleaning start');
        }
        if ($this->options['max_age'] == 0) {
            return;
        }
        $this->remove_older_than(time() - $this->options['max_age'] * 3600);
        if (HYPER_CACHE_LOG) {
            $this->log('Periodic cache cleaning stop');
        }
    }

    function remove_older_than($time) {
        $this->_remove_older_than($time, $this->get_folder() . '/');
    }

    function _remove_older_than($time, $dir) {
        $files = glob($dir . '*', GLOB_MARK);
        if (!empty($files)) {
            foreach ($files as &$file) {
                if (substr($file, -1) == '/')
                    $this->_remove_older_than($time, $file);
                else {
                    //$this->log($file . ' ' . ($time-filemtime($file)));
                    if (@filemtime($file) < $time) {
                        //$this->log('Removing ' . $file);
                        @unlink($file);
                    }
                }
            }
        }
    }

    function get_folder() {
        if (empty($this->options['folder']))
            //return WP_CONTENT_DIR . '/cache/hyper-cache';
            return 'saekv://cache';
        else
            return $this->options['folder'];
    }

    function text_to_list($text) {
        $list = array();
        $items = explode("\n", str_replace(array("\n", "\r"), "\n", $text));
        foreach ($items as &$item) {
            $item = trim($item);
            if ($item == '')
                continue;
            $list[] = $item;
        }
        return $list;
    }

}

/*
  function hyper_cache_remove_protocol($buffer) {

  $buffer = str_ireplace('"http://', '"//', $buffer);
  $buffer = str_ireplace('\'http://', '\'//', $buffer);
  return $buffer;

  $parts = parse_url(get_home_url());
  $buffer = str_ireplace('http://' . $parts['host'], '//' . $parts['host'], $buffer);
  $buffer = str_ireplace('https://' . $parts['host'], '//' . $parts['host'], $buffer);
  return $buffer;

  }

 */

function hyper_cache_cdn_callback($matches) {
    //error_log($matches[1]);
    $parts = parse_url($matches[2]);
    //$return = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
    $return = HyperCache::$instance->options['cdn_url'] . $parts['path'];

    if (!empty($parts['query'])) {
        $return .= '?' . $parts['query'];
    }
    return $matches[1] . $return . $matches[3];
}

function hyper_cache_callback($buffer) {
    global $cache_stop, $lite_cache, $hyper_cache_stop, $hyper_cache_group, $hyper_cache_is_mobile, $hyper_cache_gzip_accepted;

    $buffer = trim($buffer);

    if (strlen($buffer) == 0) {
        return '';
    }

    $options = HyperCache::$instance->options;

    // Replace the CDN Url
    if (isset($options['cdn_enabled'])) {
        $parts = parse_url(get_option('home'));
        $base = quotemeta($parts['scheme'] . '://' . $parts['host']);
        $base = quotemeta('http://' . $parts['host']);

        $buffer = preg_replace_callback("#(<img.+src=[\"'])(" . $base . ".*)([\"'])#U", 'hyper_cache_cdn_callback', $buffer);
        $buffer = preg_replace_callback("#(<script.+src=[\"'])(" . $base . ".*)([\"'])#U", 'hyper_cache_cdn_callback', $buffer);
        $buffer = preg_replace_callback("#(<link.+href=[\"'])(" . $base . ".*)([\"'])#U", 'hyper_cache_cdn_callback', $buffer);
    }

    $buffer = apply_filters('cache_buffer', $buffer);

    if ($cache_stop || $hyper_cache_stop) {

        if (isset($options['gzip']) && $hyper_cache_gzip_accepted && function_exists('gzencode')) {
            //error_log('dsfsdfsdfsdfsdffafasfsafdsa');
            header('Cache-Control: private, max-age=0, no-cache, no-transform', false);
            header('Vary: Accept-Encoding,User-Agent');
            header('Content-Encoding: gzip');
            header('X-Hyper-Cache: gzip on the fly', false);
            return gzencode($buffer, 9);
        }
        return $buffer;
    }

    $uri = hyper_cache_sanitize_uri($_SERVER['REQUEST_URI']);

    $lc_dir = HyperCache::$instance->get_folder() . '/' . strtolower($_SERVER['HTTP_HOST']) . $uri;

    if ($hyper_cache_is_mobile) {
        // Bypass (should no need since there is that control on advanced-cache.php)
        if ($options['mobile'] == 2) {
            if (isset($options['gzip']) && $hyper_cache_gzip_accepted && function_exists('gzencode')) {
                header('Cache-Control: private, max-age=0, no-cache, no-transform', false);
                header('Vary: Accept-Encoding,User-Agent');
                header('Content-Encoding: gzip');
                header('X-Hyper-Cache: mobile, gzip on the fly', false);
                return gzencode($buffer, 9);
            }
            return $buffer;
        }
    }

    if (is_404()) {
        $lc_file = HyperCache::$instance->get_folder() . '' . strtolower($_SERVER['HTTP_HOST']) . '/404.html';
        $lc_file_ts='cache/'.strtolower($_SERVER['HTTP_HOST']).'/404.html-ts';
    } else {
        $lc_file = $lc_dir . 'index' . $hyper_cache_group . '.html';
        $lc_file_ts='cache/'.strtolower($_SERVER['HTTP_HOST']). $uri .'index'.$hyper_cache_group.'.html-ts';
/*
        if (!is_dir($lc_dir)) {
            wp_mkdir_p($lc_dir);
        }*/
    }

    if (!isset($options['reject_comment_authors']) && is_singular() && !is_feed() && !is_user_logged_in()) {
        $script = '<script>';
        $script .= 'function lc_get_cookie(name) {';
        $script .= 'var c = document.cookie;';
        $script .= 'if (c.indexOf(name) != -1) {';
        $script .= 'var x = c.indexOf(name)+name.length+1;';
        $script .= 'var y = c.indexOf(";",x);';
        $script .= 'if (y < 0) y = c.length;';
        $script .= 'return decodeURIComponent(c.substring(x,y));';
        $script .= '} else return "";}';
        $script .= 'if ((d = document.getElementById("commentform")) != null) { e = d.elements;';
        $script .= 'var z = lc_get_cookie("comment_author_email_' . COOKIEHASH . '");';
        $script .= 'if (z != "") e["email"].value = z;';
        $script .= 'z = lc_get_cookie("comment_author_' . COOKIEHASH . '");';
        $script .= 'if (z != "") e["author"].value = z.replace(/\+/g, " ");';
        $script .= 'z = lc_get_cookie("comment_author_url_' . COOKIEHASH . '");';
        $script .= 'if (z != "") e["url"].value = z;';
        $script .= '}';
        $script .= '</script>';
        $x = strrpos($buffer, '</body>');
        if ($x) {
            $buffer = substr($buffer, 0, $x) . $script . '</body></html>';
        } else {
            $buffer .= $script;
        }
    }

    file_put_contents($lc_file, $buffer . '<!-- hyper cache for sae ' . date('Y-m-d h:i:s') . ' -->');
    //file_put_contents($lc_file_ts,strval(time()));
    $kv = new SaeKV();
// 初始化SaeKV对象
$ret = $kv->init();
$ret=$kv->set($lc_file_ts,strval(time()));

    // Saves the gzipped version
    if (isset($options['gzip'])) {
        $gzf = gzopen($lc_file . '.gz', 'wb9');
        gzwrite($gzf, $buffer . '<!-- hyper cache gzip for sae ' . date('Y-m-d h:i:s') . ' -->');
        gzclose($gzf);
    }
    return $buffer;
}

if (!function_exists('hyper_cache_sanitize_uri')) {

    function hyper_cache_sanitize_uri($uri) {
        if (HYPER_CACHE_LOG) {
            $this->log('URI: ' . $uri);
        }
        $uri = preg_replace('/[^a-zA-Z0-9\/\-_]+/', '_', $uri);
        $uri = preg_replace('/\/+/', '/', $uri);
        //$uri = rtrim($uri, '.-_/');
        if (empty($uri) || $uri[0] != '/') {
            $uri = '/' . $uri;
        }
        if (strlen($uri) > 1 && substr($uri, -1, 1) == '/') {
            $uri = rtrim($uri, '/') . '_';
        }
        if (HYPER_CACHE_LOG) {
            $this->log('Sanitized URI: ' . $uri);
        }
        return $uri;
    }

}
