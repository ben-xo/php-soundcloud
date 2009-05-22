<?php
/**
 * A minimalistic API wrapper for SoundCloud written in PHP.
 * For further reference and example of usage see: http://github.com/mptre/php-soundcloud/
 *
 * @author Anton Lindqvist <anton@qvister.se>
 * @version 0.1
 */

class Soundcloud {
  private $key;
  private $me;
  private $username;
  private $password;
  private $url;
  
  public function __construct($username, $password, $key = NULL) {
    $this->username = $username;
    $this->password = $password;
    $this->key = $key;
    $this->me = ($key == 'me') ? true : false;
    $this->url = 'http://api.sandbox-soundcloud.com';
  }
  
  public function __get($key) {
    return new Soundcloud($this->username, $this->password, $key);
  }
  
  public function __call($method, $args) {
    $args = (count($args) && is_array($args)) ? $args[0] : array();
    $options = array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL => sprintf('%s/%s/', $this->url, $this->key),
      CURLOPT_USERPWD => sprintf('%s:%s', $this->username, $this->password)
    );
    
    // Handle me and users method.
    if (!in_array($this->key, array('tracks'))) {
      $options[CURLOPT_URL] .= ($args['user_id']) ? $args['user_id'] .'/' : NULL;
      $options[CURLOPT_URL] .= ($method != 'basic') ? $method .'/' : NULL;
    }
    
    // Handle tracks search method.
    if (is_array($args['query'])) {
      $options[CURLOPT_URL] = preg_replace('/\/$/', '?', $options[CURLOPT_URL]);
      $options[CURLOPT_URL] .= http_build_query($args['query']);
    }
    
    // Handle POST, PUT and DELETE requests.
    foreach ($args as $key => $val) {
      if (in_array($key, array('delete', 'post', 'put'))) {
        if ($key == 'post')
          $options[CURLOPT_POST] = 1;
        else
          $options[CURLOPT_CUSTOMREQUEST] = strtoupper($key);
        
        $options[CURLOPT_POSTFIELDS] = ($args['fields']) ? $args['fields'] : '';
      }
      // Probably more efficient to use a regex here instead.
      elseif (in_array($key, array('comment_id', 'contact_id', 'event_id', 'track_id'))) {
        $options[CURLOPT_URL] .= $args[$key] .'/';
      }
    }
    
    if (in_array($this->key, array('tracks')) && $method != 'basic')
      $options[CURLOPT_URL] .= $method;
    
    $ch = curl_init();
    curl_setopt_array($ch, $options);
    $data = curl_exec($ch);
    $meta = curl_getinfo($ch);
    
    if (in_array($meta['http_code'], array(200, 201, 303))) {
      return (strlen($data) <= 1) ? true : $data;
    } else {
      // Throw error.
      throw new SoundcloudException(sprintf('Response code: %d from %s', $meta['http_code'], $options[CURLOPT_URL]));
    }
  }
  
  public function __destruct() {
    $this->username = null;
    $this->password = null;
    $this->key = null;
    $this->me = null;
    $this->url = null;
  }
}

class SoundcloudException extends Exception {
  // Kthxbye.
}
?>