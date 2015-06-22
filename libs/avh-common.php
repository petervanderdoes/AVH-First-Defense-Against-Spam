<?php
if (!defined('AVH_FRAMEWORK')) {
    die('You are not allowed to call this page directly.');
}
if (!class_exists('AVH_Common')) {

    final class AVH_Common
    {
        /**
         *
         * @var array preferred order of attributes
         */
        // @format_off
        public static $attribute_order = array(
            'action',
            'method',
            'type',
            'id',
            'name',
            'value',
            'href',
            'src',
            'width',
            'height',
            'cols',
            'rows',
            'size',
            'maxlength',
            'rel',
            'media',
            'accept-charset',
            'accept',
            'tabindex',
            'accesskey',
            'alt',
            'title',
            'class',
            'style',
            'selected',
            'checked',
            'readonly',
            'disabled',
        );
        // @format_on
        /**
         * Sends the email
         *
         * @param       $to
         * @param       $subject
         * @param       $message
         * @param array $footer
         */
        public static function sendMail($to, $subject, $message, $footer = array())
        {
            $message = array_merge($message, $footer);
            $msg = '';
            foreach ($message as $line) {
                $msg .= $line . "\r\n";
            }
            wp_mail($to, $subject, $msg);

            return;
        }

        /**
         * Returns the wordpress version
         * Note: 2.7.x will return 2.7
         *
         * @return float
         */
        public static function getWordpressVersion()
        {
            static $_version = null;
            if (!isset($_version)) {
                // Include WordPress version
                require(ABSPATH . WPINC . '/version.php');
                $_version = (float) $wp_version;
            }

            return $_version;
        }

        /**
         * Determines if the current version of PHP is greater then the supplied value
         *
         * @param string $version The needed PHP version
         *
         * @return bool False if the version is lower than the given version.
         */
        public static function isPHP($version = '5.0.0')
        {
            static $_is_php = null;
            $version = (string) $version;
            if (!isset($_is_php[$version])) {
                $_is_php[$version] = (version_compare(PHP_VERSION, $version) < 0) ? false : true;
            }

            return $_is_php[$version];
        }

        /**
         * Get the base directory of a directory structure
         *
         * @param string $directory
         *
         * @return string
         *
         */
        public static function getBaseDirectory($directory)
        {
            // get public directory structure eg "/top/second/third"
            $public_directory = dirname($directory);
            // place each directory into array
            $directory_array = explode('/', $public_directory);
            // get highest or top level in array of directory strings
            $public_base = max($directory_array);

            return $public_base;
        }

        // Returns the url of the plugin's root folder
        public function get_base_url()
        {
            $folder = basename(dirname(__FILE__));

            return plugins_url($folder);
        }

        /**
         * This function will take an IP address or IP number in almost any format (that I can think of) and will return it's decimal unsigned equivalent, as a string.
         * Kind                => Input                => Return        => long2ip(Return)
         * DottedQuadDec    => 192.168.255.109        => 3232300909    => 192.168.255.109
         * PosIntStr        => 3232300909            => 3232300909    => 192.168.255.109
         * NegIntStr        => -1062666387            => 3232300909    => 192.168.255.109
         * PosInt            => 3232300909            => 3232300909    => 192.168.255.109
         * NegInt            => -1062666387            => 3232300909    => 192.168.255.109
         * DottedQuadHex    => 0xc0.0xA8.0xFF.0x6D    => 0    => 0.0.0.0
         * DottedQuadOct    => 0300.0250.0377.0155    => 0    => 0.0.0.0
         * HexIntStr        => 0xC0A8FF6D            => 0    => 0.0.0.0
         * HexInt            => 3232300909 => 3232300909    => 192.168.255.109
         *
         * @param string /numeric $ip
         *
         * @return string
         */
        public static function getIp2long($ip)
        {
            if (is_numeric($ip)) {
                $return = sprintf("%u", floatval($ip));
            } else {
                $return = sprintf("%u", floatval(ip2long($ip)));
            }

            return $return;
        }

        /**
         * Compiles an array of HTML attributes into an attribute string.
         * Attributes will be sorted using HTML::$attribute_order for consistency.
         *
         * echo '<div'.HTML::attributes($attrs).'>'.$content.'</div>';
         *
         * @param array $attributes attribute list
         *
         * @return string
         */
        public static function attributes(array $attributes = null)
        {
            if (empty($attributes)) {
                return '';
            }

            $sorted = array();
            foreach (self::$attribute_order as $key) {
                if (isset($attributes[$key])) {
                    // Add the attribute to the sorted list
                    $sorted[$key] = $attributes[$key];
                }
            }

            // Combine the sorted attributes
            $attributes = $sorted + $attributes;

            $compiled = '';
            foreach ($attributes as $key => $value) {
                if ($value === null) {
                    // Skip attributes that have NULL values
                    continue;
                }

                if (is_int($key)) {
                    // Assume non-associative keys are mirrored attributes
                    $key = $value;
                }

                // Add the attribute key
                $compiled .= ' ' . $key;

                if ($value) {
                    // Add the attribute value
                    $compiled .= '="' . esc_attr($value) . '"';
                }
            }

            return $compiled;
        }
    }
}
