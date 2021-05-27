<?php


namespace Pressmind;


class HelperFunctions
{

    const HOUSING_OPTION_STATUS_ACTIVE = 1;
    const HOUSING_OPTION_STATUS_BOOKED_OUT = 0;
    const HOUSING_OPTION_STATUS_LOW = 2;
    const HOUSING_OPTION_STATUS_REQUEST = 3;
    const HOUSING_OPTION_STATUS_STOP = 4;
    const HOUSING_OPTION_STATUS_HIDE = 5;

    const MEDIA_OBJECT_VISIBILITY_NOBODY = 10;
    const MEDIA_OBJECT_VISIBILITY_PUBLIC = 30;
    const MEDIA_OBJECT_VISIBILITY_EXTRANET = 40;
    const MEDIA_OBJECT_VISIBILITY_INTRANET = 50;

    const MEDIA_OBJECT_STATE_DRAFT = 30;
    const MEDIA_OBJECT_STATE_PENDING_REVIEW = 40;
    const MEDIA_OBJECT_STATE_OK = 50;
    const MEDIA_OBJECT_STATE_CLOSED = 60;
    const MEDIA_OBJECT_STATE_CLOSED_REASON_AGE = 70;
    const MEDIA_OBJECT_STATE_CLOSED_REASON_LAW = 80;
    const MEDIA_OBJECT_STATE_CLOSED_REASON_BAD_QUALITY = 90;
    const MEDIA_OBJECT_STATE_CLOSED_REASON_DUPLICATE_CONTENT = 100;
    const MEDIA_OBJECT_STATE_CLOSED_REASON_TECHNICAL_ERROR = 110;
    const MEDIA_OBJECT_STATE_IMPORTED = 200;
    /**
     * Setting locales on different server systems is pain
     * so here's a little function to ease a programmers mind
     * @param integer $day
     * @param string $type
     * @param string $lang
     * @return mixed
     */
    static function dayNumberToLocalDayName($day, $type = 'full', $lang = 'de') {
        $map = array(
            'de' => array(
                'short' => array(
                    1 => 'Mo',
                    2 => 'Di',
                    3 => 'Mi',
                    4 => 'Do',
                    5 => 'Fr',
                    6 => 'Sa',
                    7 => 'So'
                ),
                'middle' => array(
                    1 => 'Mon',
                    2 => 'Die',
                    3 => 'Mit',
                    4 => 'Don',
                    5 => 'Fre',
                    6 => 'Sam',
                    7 => 'Son'
                ),
                'full' => array(
                    1 => 'Montag',
                    2 => 'Dienstag',
                    3 => 'Mittwoch',
                    4 => 'Donnerstag',
                    5 => 'Freitag',
                    6 => 'Samstag',
                    7 => 'Sonntag'
                )
            )
        );
        return $map[$lang][$type][$day];
    }

    /**
     * Setting locales on different server systems is pain
     * so here's a little function to ease a programmers mind
     * @param integer $month
     * @param string $type
     * @param string $lang
     * @return mixed
     */
    static function monthNumberToLocalMonthName($month, $type = 'full', $lang = 'de') {
        $map = array(
            'de' => array(
                'short' => array(
                    1 => 'Jan',
                    2 => 'Feb',
                    3 => 'Mär',
                    4 => 'Apr',
                    5 => 'Mai',
                    6 => 'Jun',
                    7 => 'Jul',
                    8 => 'Aug',
                    9 => 'Sep',
                    10 => 'Okt',
                    11 => 'Nov',
                    12 => 'Dez',
                ),
                'full' => array(
                    1 => 'Januar',
                    2 => 'Februar',
                    3 => 'März',
                    4 => 'April',
                    5 => 'Mai',
                    6 => 'Juni',
                    7 => 'Juli',
                    8 => 'August',
                    9 => 'September',
                    10 => 'Oktober',
                    11 => 'November',
                    12 => 'Dezember',
                )
            )
        );
        return $map[$lang][$type][$month];
    }

    /**
     * Replaces the most common latin special chars with equivalent ascii characters
     * Can be used to replace ö with oe, etc. before sanitizing for filesystem names an other stuff
     * @param string $string
     * @return string
     */
    static function replaceLatinSpecialChars($string)
    {
        $find = ['ß','à','á','â','ã','ä','å','æ','ç','è','é','ê','ë','ì','í','î','ï','ð','ñ','ò','ó','ô','õ','ö','ø','ù','ú','û','ü','ý','þ','ÿ','À','Á','Â','Ã','Ä','Å','Æ','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ð','Ñ','Ò','Ó','Ô','Õ','Ö','Ø','Ù','Ú','Û','Ü','Ý','€','@'];
        $replace = ['ss','a','a','a','a','ae','a','ae','c','e','e','e','e','i','i','i','i','o','n','o','o','o','o','oe','o','u','u','u','ue','y','p','y','A','A','A','A','AE','A','AE','C','E','E','E','E','E','E','I','I','D','N','O','O','O','O','OE','O','U','U','U','UE','Y','EUR','AT'];
        return str_replace($find, $replace, $string);
    }

    /**
     * Converts a given human readable string to a reliable machine usable string
     * @example human_to_machine('This is a human sentence.') will return 'this_is_a_human_sentence'
     * @param $human_name
     * @return string
     */
    static public function human_to_machine($human_name) {
        return preg_replace(
            array(
                '/[^a-zA-Z0-9]+/',
                '/-+/',
                '/^-+/',
                '/-+$/',
            ),
            array(
                '_',
                '_',
                '',
                ''),
            self::replaceLatinSpecialChars(strtolower($human_name))
        );
    }

    /**
     * Creates a random string of the given length
     * $pType can be one of these:
     * test: always returns the same string = "test"
     * any: returns a random string, which can contain strange characters
     * alphanum: returns a random string containing alphanumerics only
     * standard: same as alphanum, but not including l10O (lower L, one, zero, upper O)
     *
     * @param int $pLength
     * @param string $pType
     * @return boolean|string
     */
    public static function randomString($pLength=8, $pType='standard')
    {
        $pRanges = [];
        $return = false;
        switch($pType) {
            case 'test':
                $return = 'test';
                break;
            case 'any':
                $pRanges = ['40-59','61-91','93-126'];
                break;
            case 'standard':
                $pRanges = ['65-78','80-90','97-107','109-122','50-57'];
                break;
            case 'alnum':
                $pRanges = ['65-90','97-122','48-57'];
                break;
            default:
                $return = false;
                $pRanges = [];
        }
        $pNumRanges=count($pRanges);
        if($pNumRanges > 0) {
            $pString='';
            for ($i = 1; $i <= $pLength; $i++) {
                $r=mt_rand(0,$pNumRanges-1);
                list($min,$max)=explode('-',$pRanges[$r]);
                $pString.=chr(mt_rand($min,$max));
            }
            $return = $pString;
        }
        return $return;
    }

    /**
     * @param array $path_items
     * @return string
     */
    public static function buildPathString($path_items)
    {
        return implode(DIRECTORY_SEPARATOR, $path_items);
    }

    /**
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public static function findObjectInArray($array, $key, $value)
    {
        foreach ($array as $object) {
            if($object->$key === $value) {
                return $object;
            }
        }
    }

    /**
     * Escape User input
     * @param array|string $pInput
     * @return array|string
     */
    public static function escapeUserInput($pInput, $pStripTags = true)
    {
        if(is_array($pInput)) {
            $return = [];
            foreach ($pInput as $key=>$value) {
                $return[$key] = self::escapeString($value, $pStripTags);
            }
        } else {
            $return = self::escapeString($pInput, $pStripTags);
        }
        return $return;
    }

    /**
     * Escape a string
     * @param string $pString
     * @param boolean $pStripTags
     * @return string
     */
    public static function escapeString($pString, $pStripTags = true)
    {
        $return = '';
        try {
            if(true == $pStripTags) {
                $return = strip_tags($pString);
            } else {
                $return = str_replace('<p>','',$pString);
                $return = str_replace('</p>','',$return);
            }
            //$return = addslashes($return);
            $return = str_ireplace('SELECT','',$return);
            $return = str_ireplace('INSERT','',$return);
            $return = str_ireplace('DELETE','',$return);
            $return = str_ireplace('TRUNCATE','',$return);
            $return = str_ireplace('GRANT','',$return);
            $return = str_ireplace('UPDATE','',$return);
            $return = str_ireplace('FROM','',$return);
        } catch(Exception $e) {
            $return = $e->getMessage();
        }

        return $return;
    }

    /**
     * trims text to a space then adds ellipses if desired
     * @param string $pInput text to trim
     * @param int $pLength in characters to trim to
     * @param string|bool $pEllipses ellipses are to be added
     * @param bool $pStripHtml if html tags are to be stripped
     * @return string
     */
    public static function trimText($pInput, $pLength, $pEllipses = '...', $pStripHtml = true)
    {
        //strip tags, if desired
        if ($pStripHtml) {
            $pInput = strip_tags($pInput);
        }

        //no need to trim, already shorter than trim length
        if (strlen($pInput) <= $pLength) {
            return $pInput;
        }

        //find last space within length
        $last_space = strrpos(substr($pInput, 0, $pLength), ' ');
        $trimmed_text = substr($pInput, 0, $last_space);

        //add ellipses (...)
        if (false != $pEllipses) {
            $trimmed_text .= ' ' . $pEllipses;
        }

        return $trimmed_text;
    }

    /**
     * Check if a given string is valid json
     * @param $string
     * @return bool
     */
    public static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    /**
     * Check if a given value is an integer
     * @param string $val
     * @return bool
     */
    public static function isInteger($val)
    {
        if (!is_scalar($val) || is_bool($val)) {
            return false;
        }
        if (is_float($val + 0) && ($val + 0) > PHP_INT_MAX) {
            return false;
        }
        return is_float($val) ? false : boolval(preg_match('~^((?:\+|-)?[0-9]+)$~', $val));
    }

    /**
     * Check if a given value is an float
     * @param string $val
     * @return bool
     */
    public static function isFloat($val)
    {
        if (!is_scalar($val)) {
            return false;
        }
        return is_float($val + 0);
    }

    /**
     * Parses a german style number formatted string (e.g. 12,2334) into valid float
     * @param string $str
     * @return float
     * @throws \Exception
     */
    public static function strToFloat($str)
    {
        if (is_int($str) || is_float($str)) {
            return floatval($str);
        }
        if (!is_string($str)) {
            throw new \Exception('String expected but received '. gettype($str) . '.');
        }
        $str = trim($str);
        if (!preg_match('/^(\-|\+)?[0-9][0-9\,\.]*/', $str)) {
            throw new \Exception("Could not convert string to float. Given string does not match expected number format: " . $str);
        }

        $last = max(strrpos($str, ','), strrpos($str, '.'));
        if ($last!==false) {
            $str = strtr($str, ',.', 'XX');
            $str[$last] = '.';
            $str = str_replace('X', '', $str);
        }
        return (float)$str;
    }

    /**
     * Will set the value of an multidimension array key by a string path
     * @example
     * assignArrayByPath($myarray, 'foo.bar.baz', 'value of key baz', '.')
     * Array
     *   (
     *      [foo] => Array
     *      (
     *          [bar] => Array
     *           (
     *               [baz] => value of key baz
     *           )
     *       )
     *   )
     * @param $arr
     * @param $path
     * @param $value
     * @param string $separator
     */
    public static function assignArrayByPath(&$arr, $path, $value, $separator = '.')
    {
        $keys = explode($separator, $path);
        foreach ($keys as $key) {
            $arr = &$arr[$key];
        }
        $arr = $value;
    }

    /**
     * @param $data
     * @param $path
     * @param $result
     * @param string $seperator
     * @return bool
     */
    public static function getArrayByPath($data, $path, $separator = '.')
    {

        $found = true;

        $path = explode($separator, $path);

        for ($x=0; ($x < count($path) and $found); $x++){

            $key = $path[$x];

            if (isset($data[$key])){
                $data = $data[$key];
            }
            else { $found = false; }
        }

        return $data;
    }

    /**
     * Displays a debug message when a debug GET Variable is set
     * The debug variable has to be 1 by default, but can be set to whatever wanted
     * HelperFunctions::debug($pBookingObject, 'BookingObject', 'booking', true)
     * => will display a position: fixed message with Title BookingObject when the GET Variable debug=booking is set
     * @param mixed $pObject
     * @param string $pTitle
     * @param int $pDebugVar
     * @param bool $pDisplayFixed
     */
    public static function debug($pObject, $pTitle = null, $pDebugVar = 1, $pDisplayFixed = false) {
        $html = [];
        if(false == $pDisplayFixed) {
            $html[] = '<div>';
        } else {
            $html[] = '<div style="position: fixed; top: 32px; left: 0; width: 100%; z-index: 1000000; background: white; overflow: auto; max-height: 600px;">';
        }
        if(!is_null($pTitle)) {
            $html[] = '<h3>' . $pTitle . '</h3>';
        }
        $html[] = '<pre>';
        $html[] = print_r($pObject, true);
        $html[] = '</pre>';
        $html[] = '</div>';
        if(isset($_GET['debug']) && $_GET['debug'] == $pDebugVar) {
            echo implode('', $html);
        }
    }

    public static function number_format($number)
    {
        return number_format($number, 2, ',', '.');
    }

    /**
     * @param string $mime_type
     * @return bool|string
     */
    public static function getExtensionFromMimeType($mime_type) {
        $mime_map = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'audio/mp4'                                                                 => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'font/otf'                                                                  => 'otf',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'font/ttf'                                                                  => 'ttf',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'image/webp'                                                                => 'webp',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'font/woff'                                                                 => 'woff',
            'font/woff2'                                                                => 'woff2',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];

        return isset($mime_map[$mime_type]) ? $mime_map[$mime_type] : false;
    }

    public static function replaceConstantsFromConfig($string)
    {
        $config = Registry::getInstance()->get('config');
        return str_replace(
            ['BASE_PATH','APPLICATION_PATH','WEBSERVER_DOCUMENT_ROOT','WEBSERVER_HTTP','DATABASE_NAME'],
            [BASE_PATH,APPLICATION_PATH,WEBSERVER_DOCUMENT_ROOT,WEBSERVER_HTTP, $config['database']['dbname']],
            $string
        );
    }

    /**
     * @param array $arguments
     * @return array
     */
    public static function parseCliArguments($arguments) {
        $args_list = [];
        $args_dictionary = [];
        for ($i = 0; $i < count($arguments); $i++) {
            if(substr($arguments[$i], 0, 2) == '--') {
                if(isset($arguments[$i + 1]) && substr($arguments[$i + 1], 0, 1) != '-') {
                    $args_dictionary[substr($arguments[$i], 2)] = $arguments[$i + 1];
                    $i++;
                } else {
                    $args_list[] = substr($arguments[$i], 2);
                }
            } else if(substr($arguments[$i], 0, 1) == '-') {
                $arg = substr($arguments[$i], 1);
                for($j = 0; $j < strlen($arg); $j++) {
                    $args_list[] = substr($arg, $j, 1);
                }
            } else {
                $args_list[] = $arguments[$i];
            }
        }
        return [
            'list' => $args_list,
            'dictionary' => $args_dictionary
        ];
    }
}
