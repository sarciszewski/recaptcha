<?php

namespace Sarciszewski;

/* This is a PHP library that handles calling reCAPTCHA.
 *    - Documentation and latest version
 *          http://recaptcha.net/plugins/php/
 *    - Get a reCAPTCHA API Key
 *          https://www.google.com/recaptcha/admin/create
 *    - Discussion group
 *          http://groups.google.com/group/recaptcha
 *
 * Copyright (c) 2007 reCAPTCHA -- http://recaptcha.net
 * AUTHORS:
 *   Scott Arciszewski
 *   Mike Crawford
 *   Ben Maurer
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
class ReCaptcha
{
    private $public_key = null;
    private $private_key = null;

    /**
     * The reCAPTCHA server URL's
     */
    const API_SERVER = "http://www.google.com/recaptcha/api";
    const API_SECURE_SERVER = "https://www.google.com/recaptcha/api";
    const VERIFY_SERVER = "www.google.com";

    public function __construct($public = null, $private = null)
    {
        if (!empty($public)) {
            $this->public_key = $public;
        }
        if (!empty($private)) {
            $this->private_key = $private;
        }
    }

    /**
     * Encodes the given data into a query string format
     * @param $data - array of string elements to be encoded
     * @return string - encoded request
     */
    protected static function _qsencode($data)
    {
        $req = "";
        foreach ($data as $key => $value) {
                $req .= $key . '=' . \urlencode(\stripslashes($value)) . '&';
        }

        // Cut the last '&'
        $req = \substr($req, 0, \strlen($req)-1);
        return $req;
    }

    /**
     * Submits an HTTP POST to a reCAPTCHA server
     * @param string $host
     * @param string $path
     * @param array $data
     * @param int port
     * @return array response
     * @throws ReCaptchaException
     */
    protected static function _http_post($host, $path, $data, $port = 80)
    {
        $req = self::_qsencode($data);
        $http_request  = "POST $path HTTP/1.0\r\n";
        $http_request .= "Host: $host\r\n";
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= "Content-Length: " . \strlen($req) . "\r\n";
        $http_request .= "User-Agent: reCAPTCHA/PHP\r\n";
        $http_request .= "\r\n";
        $http_request .= $req;

        $response = '';
        $fs = @\fsockopen($host, $port, $errno, $errstr, 10);
        if ($fs === false) {
            throw new ReCaptchaException('Could not open socket');
        }

        \fwrite($fs, $http_request);

        while (!\feof($fs)) {
            $response .= \fgets($fs, 1160); // One TCP-IP packet
        }
        \fclose($fs);
        $response = \explode("\r\n\r\n", $response, 2);

        return $response;
    }

    /**
     * Escape a string to place in an HTML attribute. Prevents XSS.
     *
     * @param string $string
     * @return string
     */
    public static function escapeAttribute($string)
    {
        return \htmlentities($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Gets the challenge HTML (javascript and non-javascript version).
     * This is called from the browser, and the resulting reCAPTCHA HTML widget
     * is embedded within the HTML form it was called from.
     * @param string $pubkey A public key for reCAPTCHA
     * @param string $error The error given by reCAPTCHA (optional, default is null)
     * @param boolean $use_ssl Should the request be made over ssl? (optional, default is true)
     *
     * @return string - The HTML to be embedded in the user's form.
     * @throws ReCaptchaException
     */
    public function get_html($pubkey = '', $error = null, $use_ssl = true)
    {
        if (empty($pubkey)) {
            if (empty($this->public_key)) {
                throw new ReCaptchaException(
                    "To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>"
                );
            }
            $pubkey = $this->public_key;
        }
        if ($use_ssl) {
            $server = self::API_SECURE_SERVER;
        } else {
            $server = self::API_SERVER;
        }
        $errorpart = "";
        if ($error) {
           $errorpart = "&amp;error=" . self::escapeAttribute($error);
        }
        return '<script type="text/javascript" src="'. self::escapeAttribute($server) . '/challenge?k=' . self::escapeAttribute($pubkey . $errorpart) . '"></script>

        <noscript>
            <iframe src="'. self::escapeAttribute($server) . '/noscript?k=' . self::escapeAttribute($pubkey . $errorpart) . '" height="300" width="500" frameborder="0"></iframe><br/>
            <textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
            <input type="hidden" name="recaptcha_response_field" value="manual_challenge"/>
        </noscript>';
    }

    /**
      * Calls an HTTP POST function to verify if the user's guess was correct
      * @param string $challenge
      * @param string $response
      * @param string $privkey
      * @param string $remoteip
      * @param array $extra_params an array of extra variables to post to the server
      * @return ReCaptchaResponse
      * @throws ReCaptchaException
      */
    public function check_answer(
        $challenge = '',
        $response = '',
        $privkey = '',
        $remoteip = '',
        $extra_params = array()
    ) {
        if (empty($challenge)) {
            $challenge = $_POST['recaptcha_challenge_field'];
        }
        if (empty($response)) {
            $response = $_POST['recaptcha_response_field'];
        }
        if (empty($privkey)) {
            if (empty($this->private_key)) {
                throw new ReCaptchaException(
                    "To use reCAPTCHA you must get an API key from <a href='https://www.google.com/recaptcha/admin/create'>https://www.google.com/recaptcha/admin/create</a>"
                );
            }
            $privkey = $this->private_key;
        }
        if (empty($remoteip)) {
            /*throw new ReCaptchaException(
                "For security reasons, you must pass the remote ip to reCAPTCHA"
            );*/
            $remote = $_SERVER['REMOTE_ADDR'];
        }
        // discard spam submissions
        if (empty($challenge) || empty($response)) {
                $recaptcha_response = new ReCaptchaResponse();
                $recaptcha_response->is_valid = false;
                $recaptcha_response->error = 'incorrect-captcha-sol';
                return $recaptcha_response;
        }

        $response = self::_http_post(
            self::VERIFY_SERVER,
            "/recaptcha/api/verify",
            array(
                'privatekey' => $privkey,
                'remoteip' => $remoteip,
                'challenge' => $challenge,
                'response' => $response
            ) + $extra_params
        );

        $answers = \explode("\n", $response[1]);
        $recaptcha_response = new ReCaptchaResponse();

        if (\trim($answers[0]) == 'true') {
            $recaptcha_response->is_valid = true;
        } else {
            $recaptcha_response->is_valid = false;
            $recaptcha_response->error = $answers[1];
        }
        return $recaptcha_response;
    }

    /**
     * gets a URL where the user can sign up for reCAPTCHA. If your application
     * has a configuration page where you enter a key, you should provide a link
     * using this function.
     * @param string $domain The domain where the page is hosted
     * @param string $appname The name of your application
     */
    public function get_signup_url ($domain = null, $appname = null) {
        return "https://www.google.com/recaptcha/admin/create?" . self::_qsencode (
            array (
                'domains' => $domain,
                'app' => $appname
            )
        );
    }
}
