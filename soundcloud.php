<?php
/**
 * API Wrapper for SoundCloud written in PHP with support for authication using OAuth.
 *
 * @author Anton Lindqvist <anton@qvister.se>
 * @version 1.0
 * @link http://github.com/mptre/php-soundcloud/
 */
class Soundcloud {
    const URL_API = 'http://api.soundcloud.com/';
    const URL_OAUTH = 'http://api.soundcloud.com/oauth/';

    function __construct($consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL) {
        $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
        $this->consumer = new OAuthConsumer($consumer_key, $consumer_secret);

        if (!empty($oauth_token) && !empty($oauth_token_secret)) {
            $this->token = new OAuthConsumer($oauth_token, $oauth_token_secret);
        } else {
            $this->token = NULL;
        }
    }

    function get_authorize_url($token) {
        if (is_array($token)) {
            $token = $token['oauth_token'];
        }

        return $this->_get_url('authorize') . sprintf('?oauth_token=%s', $token);
    }

    function get_request_token($oauth_callback) {
        $request = $this->request(
            $this->_get_url('request'),
            'POST',
            array('oauth_callback' => $oauth_callback)
        );
        $token = $this->_parse_response($request);

        $this->token = new OAuthConsumer(
            $token['oauth_token'],
            $token['oauth_token_secret']
        );

        return $token;
    }

    function get_access_token($token) {
        $response = $this->request(
            $this->_get_url('access'),
            'POST',
            array('oauth_verifier' => $token)
        );
        $token = $this->_parse_response($response);
        $this->token = new OAuthConsumer(
            $token['oauth_token'],
            $token['oauth_token_secret']
        );

        return $token;
    }

    function upload_track($post_data, $asset_data_mime = NULL, $artwork_data_mime = NULL) {
        $body = '';
        $boundary = '---------------------------' . md5(rand());
        $crlf = "\r\n";
        $headers = array(
            'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            'Content-Length' => '%d'
        );

        foreach ($post_data as $key => $val) {
            if (in_array($key, array('track[asset_data]', 'track[artwork_data]'))) {
                $body .= '--' . $boundary . $crlf;
                $body .= 'Content-Disposition: form-data; name="' . $key . '"; filename="' . basename($val) . '"' . $crlf;
                $body .= 'Content-Type: ' . (($key == 'track[asset_data]') ? $asset_data_mime : $artwork_data_mime) . $crlf;
                $body .= $crlf;
                $body .= file_get_contents($val) . $crlf;
            } else {
                $body .= '--' . $boundary . $crlf;
                $body .= 'Content-Disposition: form-data; name="' . $key .'"' . $crlf;
                $body .= $crlf;
                $body .= $val . $crlf;
            }
        }

        $body .= '--' . $boundary . '--' . $crlf;
        $headers['Content-Length'] = sprintf($headers['Content-Length'], strlen($body));

        return $this->request('tracks', 'POST', $body, $headers);
    }

    function request($resource, $method = 'GET', $args = array(), $headers = NULL) {
        if (!preg_match('/http:\/\//', $resource)) {
            $url = self::URL_API . $resource;
        } else {
            $url = $resource;
        }

        if (stristr($headers['Content-Type'], 'multipart/form-data')) {
            $body = FALSE;
        } elseif (stristr($headers['Content-Type'], 'application/xml')) {
            $body = FALSE;
        } else {
            $body = TRUE;
        }

        $request = OAuthRequest::from_consumer_and_token(
            $this->consumer,
            $this->token,
            $method,
            $url,
            ($body === TRUE) ? $args : NULL
        );
        $request->sign_request($this->sha1_method, $this->consumer, $this->token);

        return $this->_curl(
            $request->get_normalized_http_url(),
            $request,
            $args,
            $headers
        );
    }

    private function _build_header($headers) {
        $h = array();

        if (count($headers) > 0) {
            foreach ($headers as $key => $val) {
                $h[] = $key . ': ' . $val;
            }

            return $h;
        } else {
            return $headers;
        }
    }

    private function _curl($url, $request, $post_data = NULL, $headers = NULL) {
        $ch = curl_init();
        $mime = (stristr($headers['Content-Type'], 'multipart/form-data')) ? TRUE : FALSE;
        $headers = (is_array($headers)) ? $this->_build_header($headers) : array();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 0,
            CURLOPT_RETURNTRANSFER => 1
        );

        if (in_array($request->get_normalized_http_method(), array('DELETE', 'PUT'))) {
            $options[CURLOPT_CUSTOMREQUEST] = $request->get_normalized_http_method();
            $options[CURLOPT_POSTFIELDS] = '';
        }

        if (is_array($post_data) && count($post_data) > 0 || $mime === TRUE) {
            $options[CURLOPT_POSTFIELDS] = (is_array($post_data) && count($post_data) == 1)
                ? ((isset($post_data[0])) ? $post_data[0] : $post_data)
                : $post_data;
            $options[CURLOPT_POST] = 1;
        }

        $headers[] = $request->to_header();
        $options[CURLOPT_HTTPHEADER] = $headers;

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $this->http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $response;
    }

    private function _get_url($type) {
        switch ($type) {
            case 'access':
                $method = 'access_token';
                break;
            case 'authorize':
                $method = 'authorize';
                break;
            case 'request':
                $method = 'request_token';
                break;
        }

        return self::URL_OAUTH . $method;
    }

    private function _parse_response($response) {
        $return = array();
        $response = split('[&]+', $response);

        foreach ($response as $r) {
            if (strstr($r, '=')) {
                list($key, $val) = split('=', $r);

                if (!empty($key) && !empty($val)) {
                    $return[urldecode($key)] = urldecode($val);
                }
            }
        }

        return (count($return) > 0) ? $return : FALSE;
    }
}