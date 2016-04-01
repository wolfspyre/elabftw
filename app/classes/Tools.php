<?php
/**
 * \Elabftw\Elabftw\Tools
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see http://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
namespace Elabftw\Elabftw;

use \RecursiveIteratorIterator;
use \RecursiveDirectoryIterator;

/**
 * Toolbelt full of useful functions
 */
class Tools
{
    /**
     * Return the date as YYYYMMDD format if no input
     * return input if it is valid
     *
     * @param string 20160521
     * @return string
     */
    public static function kdate($input = null)
    {
        // Check DATE (is != null ? is 8 in length ? is int ? is valable ?)
        if (!is_null($input)
            && ((strlen($input) == '8'))
            && self::checkId($input)) {
            // Check if day/month are good (badly)
            $datemonth = substr($input, 4, 2);
            $dateday = substr($input, 6, 2);
            if (($datemonth <= '12')
                && ($dateday <= '31')
                && ($datemonth > '0')
                && ($dateday > '0')) {
                    // SUCCESS on every test
                return $input;
            }
        }
        return date('Ymd');
    }

    /**
     * Sanitize body with a white list of allowed html tags.
     *
     * @param string $input Body to sanitize
     * @return string The sanitized body or empty string if there is no input
     */
    public static function checkBody($input)
    {
        return strip_tags(
            $input,
            "<div><br><br /><p><sub><img><sup><strong><b><em><u><a><s><font><span><ul><li><ol>
            <blockquote><h1><h2><h3><h4><h5><h6><hr><table><tr><td><code><video><audio><pagebreak>"
        );
    }

    /**
     * Converts the php.ini upload size setting to a numeric value in MB
     * Returns 2 if no value is found (using the default setting that was in there previously)
     * It also checks for the post_max_size value and return the lowest value
     *
     * @return int maximum size in MB of files allowed for upload
     */
    public static function returnMaxUploadSize()
    {
        $max_size = trim(ini_get('upload_max_filesize'));
        $post_max_size = trim(ini_get('post_max_size'));

        if (empty($max_size) || empty($post_max_size)) {
            return 2;
        }

        // assume they both have same unit to compare the values
        if (intval($post_max_size) > intval($max_size)) {
            $input = $max_size;
        } else {
            $input = $post_max_size;
        }

        // get unit
        $unit = strtolower($input[strlen($input) - 1]);

        // convert to Mb
        switch ($unit) {
            case 'g':
                $input *= 1000;
                break;
            case 'k':
                $input /= 1024;
                break;
        }

        return intval($input);
    }

    /**
     * Show the units in human format from bytes.
     *
     * @param int $bytes size in bytes
     * @return string
     */
    public static function formatBytes($bytes)
    {
        // nice display of filesize
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KiB';
        } elseif ($bytes < 1073741824) {
            return round($bytes / 1048576, 2) . ' MiB';
        } elseif ($bytes < 1099511627776) {
            return round($bytes / 1073741824, 2) . ' GiB';
        } elseif ($bytes < 1125899906842624) {
            return round($bytes / 1099511627776, 2) . ' TiB';
        } else {
            return 'That is a very big file you have there my friend.';
        }
    }

    /**
     * Take a 8 digits input and output 2014.08.16
     *
     * @param string $date Input date '20140302'
     * @param string $s an optionnal param to specify the separator
     * @return false|string The formatted string
     */
    public static function formatDate($date, $s = '.')
    {
        if (strlen($date) != 8) {
            return false;
        }
        return $date[0] . $date[1] . $date[2] . $date[3] . $s . $date['4'] . $date['5'] . $s . $date['6'] . $date['7'];
    }

    /**
     * Get the extension of a file.
     *
     * @param string $filename path of the file
     * @return string file extension
     */
    public static function getExt($filename)
    {
        // Get file extension
        $path_info = pathinfo($filename);
        // if no extension
        if (!empty($path_info['extension'])) {
            return $path_info['extension'];
        }

        return 'unknown';
    }

    /**
     * Used in login.php, login-exec.php and install/index.php
     * This is needed in the case you run an http server but people are connecting
     * through haproxy with ssl, with a http_x_forwarded_proto header.
     *
     * @return bool
     */
    public static function usingSsl()
    {
        return ((isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) == 'https'));
    }

    /**
     * Return a string 5+3+6 when fed an array
     *
     * @param array $array
     * @param string $delim An optionnal delimiter
     * @return false|string
     */
    public static function buildStringFromArray($array, $delim = '+')
    {
        $string = "";

        if (!is_array($array)) {
            return false;
        }

        foreach ($array as $i) {
            $string .= $i . $delim;
        }
        // remove last delimiter
        return rtrim($string, $delim);
    }

    /**
     * Check ID is valid (pos int)
     *
     * @param int $id
     * @throws Exception if input is not valid
     * @return int $id if pos int
     */
    public static function checkId($id)
    {
        $filter_options = array(
            'options' => array(
                'min_range' => 1
            ));
        return filter_var($id, FILTER_VALIDATE_INT, $filter_options);
    }

    /**
     * Get the size of a dir
     *
     * @param string $directory
     * @return integer
     */
    public static function dirSize($directory)
    {
        $size = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            $size += $file->getSize();
        }
        return $size;
    }

    /**
     * Get the number of files in a dir
     *
     * @param string $directory
     * @return int number of files in dir
     */
    public static function dirNum($directory)
    {
        $num = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            $num++;
        }
        return $num;
    }
}