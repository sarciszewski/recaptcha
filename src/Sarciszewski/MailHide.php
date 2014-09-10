<?php

namespace Sarciszewski;

class MailHide
{
    protected static function _aes_pad($val)
    {
        $block_size = 16;
        $numpad = $block_size - (\strlen($val) % $block_size);
        return \str_pad($val, \strlen($val) + $numpad, chr($numpad));
    }

    /* Mailhide related code */

    protected static function _aes_encrypt($val,$ky)
    {
        if (!function_exists("mcrypt_encrypt")) {
            throw new ReCaptchaException(
                "To use reCAPTCHA Mailhide, you need to have the mcrypt php module installed."
            );
        }
        $mode = MCRYPT_MODE_CBC;
        $enc = MCRYPT_RIJNDAEL_128;
        $val = self::_aes_pad($val);
        // TODO: Make this less fucking terrible --Scott
        return \mcrypt_encrypt($enc, $ky, $val, $mode, "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0");
    }

    protected static function _urlbase64 ($x)
    {
        return strtr(
            \base64_encode($x),
            '+/',
            '-_'
        );
    }

    /* gets the reCAPTCHA Mailhide url for a given email, public key and private key */
    public static function url($pubkey, $privkey, $email)
    {
        if ($pubkey == '' || $pubkey == null || $privkey == "" || $privkey == null) {
            throw new ReCaptchaException("To use reCAPTCHA Mailhide, you have to sign up for a public and private key, " .
                 "you can do so at <a href='http://www.google.com/recaptcha/mailhide/apikey'>http://www.google.com/recaptcha/mailhide/apikey</a>");
        }

        $ky = \pack('H*', $privkey);
        $cryptmail = self::_aes_encrypt ($email, $ky);

        return "http://www.google.com/recaptcha/mailhide/d?k=" .
            $pubkey .
            "&c=" .
            self::_urlbase64($cryptmail);
    }

    /**
     * gets the parts of the email to expose to the user.
     * eg, given johndoe@example,com return ["john", "example.com"].
     * the email is then displayed as john...@example.com
     */
    protected static function _email_parts($email)
    {
        $arr = \preg_split("/@/", $email );

        if (\strlen ($arr[0]) <= 4) {
            $arr[0] = \substr ($arr[0], 0, 1);
        } elseif (\strlen($arr[0]) <= 6) {
            $arr[0] = \substr($arr[0], 0, 3);
        } else {
            $arr[0] = \substr($arr[0], 0, 4);
        }
        return $arr;
    }

    /**
     * Gets html to display an email address given a public an private key.
     * to get a key, go to:
     *
     * http://www.google.com/recaptcha/mailhide/apikey
     */
    public static function html($pubkey, $privkey, $email)
    {
        $emailparts = self::_email_parts ($email);
        $url = self::url($pubkey, $privkey, $email);

        return ReCaptcha::escapeAttribute($emailparts[0]) .
            "<a href='" .
            ReCaptcha::escapeAttribute($url) .
            "' onclick=\"window.open('" .
            ReCaptcha::escapeAttribute($url) .
            "', '', 'toolbar=0,scrollbars=0,location=0,statusbar=0,menubar=0,resizable=0,width=500,height=300'); return false;\" title=\"Reveal this e-mail address\">...</a>@" .
            ReCaptcha::escapeAttribute($emailparts[1]);
    }
}
