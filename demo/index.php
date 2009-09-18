<?php
require_once 'oauth.php';
require_once 'soundcloud.php';

session_start();

// Clear the session i.e delete all stored tokens.
if (isset($_GET['logout'])) {
    session_destroy();
}

// Change these four variables, note the that temporary path must be writable by the server.
$consumer_key = 'your-consumer-key';
$consumer_secret = 'your-consumer-secret';
$callback_url = 'your-callback-url';
$tmp_path = '/absolute/path/to/directory/where/tracks/are/saved/temporary/';

// Variables used for verifying the status of the "OAuth dance".
$oauth_token = (isset($_GET['oauth_verifier']))
    ? $_GET['oauth_verifier']
    : ((isset($_SESSION['oauth_access_token'])) ? $_SESSION['oauth_access_token'] : NULL);
$oauth_request_token = (isset($_SESSION['oauth_request_token']))
    ? $_SESSION['oauth_request_token']
    : NULL;
$oauth_request_token_secret = (isset($_SESSION['oauth_request_token_secret']))
    ? $_SESSION['oauth_request_token_secret']
    : NULL;

if (isset($oauth_token) && isset($oauth_request_token) && isset($oauth_request_token_secret)) {
    // Retreive access tokens if missing.
    if (!isset($_SESSION['oauth_access_token']) && !isset($_SESSION['oauth_access_token_secret'])) {
        $soundcloud = new Soundcloud(
            $consumer_key,
            $consumer_secret,
            $_SESSION['oauth_request_token'],
            $_SESSION['oauth_request_token_secret']
        );
        $token = $soundcloud->get_access_token($oauth_token);
        $_SESSION['oauth_access_token'] = $token['oauth_token'];
        $_SESSION['oauth_access_token_secret'] = $token['oauth_token_secret'];
    }

    // Construct a fully authicated connection with SoundCloud.
    $soundcloud = new Soundcloud(
        $consumer_key,
        $consumer_secret,
        $_SESSION['oauth_access_token'],
        $_SESSION['oauth_access_token_secret']
    );

    // Get basic info about the authicated visitor.
    $me = $soundcloud->request('me');
    $me = new SimpleXMLElement($me);
    $me = get_object_vars($me);

    // If a track is submitted.
    if (isset($_POST['submit'])) {
        // We have to make sure it's a valid and supported format by SoundCloud.
        // Note that you also can include artwork for your tracks. Use the same
        // procedure as for the tracks. PNG, JPG, GIF allowed and a max size of 5MB.
        // The artwork field is called track[artwork_data].
        $mimes = array(
            'aac' => 'video/mp4',
            'aiff' => 'audio/x-aiff',
            'flac' => 'audio/flac',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/x-wav'
        );
        $extension = explode('.', $_FILES['file']['name']);
        $extension = (isset($extension[count($extension) - 1]))
            ? $extension[count($extension) - 1]
            : NULL;
        $mime = (isset($mimes[$extension])) ? $mimes[$extension] : NULL;

        if (isset($mime)) {
            $tmp_file = $tmp_path . $_FILES['file']['name'];

            // Store the track temporary.
            if (move_uploaded_file($_FILES['file']['tmp_name'], $tmp_file)) {
                $post_data = array(
                    'track[title]' => stripslashes($_POST['title']),
                    'track[asset_data]' => realpath($tmp_file),
                    'track[sharing]' => 'private'
                );

                if ($response = $soundcloud->upload_track($post_data, $mime)) {
                    $response = new SimpleXMLElement($response);
                    $response = get_object_vars($response);
                    $message = 'Success! <a href="' . $response['permalink-url'] . '">Your track</a> has been uploaded!';

                    // Delete the temporary file.
                    unlink(realpath($tmp_file));
                } else {
                    $message = 'Something went wrong while talking to SoundCloud, please try again.';
                }
            } else {
                $message = 'Couldn\'t move file, make sure the temporary path is writable by the server.';
            }
        } else {
            $message = 'SoundCloud support .mp3, .aiff, .wav, .flac, .aac, and .ogg files. Please select a different file.';
        }
    }
} else {
    // This is the first step in the "OAuth dance" where we ask the visitior to authicate himself.
    $soundcloud = new Soundcloud($consumer_key, $consumer_secret);
    $token = $soundcloud->get_request_token($callback_url);

    $_SESSION['oauth_request_token'] = $token['oauth_token'];
    $_SESSION['oauth_request_token_secret'] = $token['oauth_token_secret'];

    $login = $soundcloud->get_authorize_url($token['oauth_token']);
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
  "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>SoundCloud PHP API Wrapper</title>
    <meta name="author" content="Anton Lindqvist" />
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <link rel="stylesheet" type="text/css" href="http://yui.yahooapis.com/2.7.0/build/reset/reset-min.css" />
    <link rel="stylesheet" type="text/css" href="assets/css/style.css" />
</head>
<body>
    <div id="wrapper">
        <div id="content">
            <?php if (isset($me)): ?>
                <a class="logout" href="?logout=true">logout</a>
            <?php endif; ?>
            <div id="header">
                <h1>SoundCloud PHP API Wrapper</h1>
            </div>
            <?php if (isset($login)): ?>
            <h2>What is this?</h2>
            <p>This is a basic demo of the <a href="http://github.com/mptre/php-soundcloud/" rel="external">SoundCloud PHP API Wrapper</a> which lets you login to your SoundCloud account using <a href="http://oauth.net/" rel="external">OAuth</a>.</p>
            <p>Once logged in the user can view his profile and upload a new track to his account. The track will be kept private by default.</p>
            <p>Note that this demo neither save your login credentials or account information.</p>
            <p>For further reference see the documentation or take a look at code for this demo over at <a href="#" rel="external">GitHub</a>.</p>
            <p>The demo and API Wrapper is developed by <a href="http://qvister.se" rel="external">Anton Lindqvist</a>. And a big applause for <a href="http://github.com/hugowetterberg">Hugo Wetterberg</a> who help me out big time!</p>
            <h2>How to start?</h2>
            <p>
                <a class="button" href="<?php echo $login; ?>">login with your SoundCloud account</a>
            </p>
            <?php elseif (isset($me)): ?>
                <h2>Your profile</h2>
                <div id="profile">
                    <div class="left">
                        <p>
                            <img src="<?php echo $me['avatar-url']; ?>" width="75" height="75" alt="" />
                            <div class="avatar"></div>
                        </p>
                    </div>
                    <div class="right">
                        <h2>
                            <a href="<?php echo $me['permalink-url']; ?>"><?php echo $me['permalink']; ?></a>
                        </h2>
                        <p><?php echo $me['full-name'] ?>, <?php echo $me['city']; ?>, <?php echo $me['country']; ?></p>
                        <p>You have <?php echo $me['track-count']; ?> <?php echo ($me['track-count'] == 1) ? 'track' : 'tracks'; ?>.</p>
                    </div>
                    <div class="clear"></div>
                </div>
                <h2>Upload a new track</h2>
                <form action="" method="post" enctype="multipart/form-data">
                    <p>
                        <label for="title">Track title</label>
                        <input class="text" type="text" name="title" id="title" />
                    </p>
                    <p>
                        <label for="file">File</label>
                        <input class="file" type="file" name="file" value="" id="file" />
                    </p>
                    <p class="center">
                        <input class="submit" type="submit" name="submit" value="Upload" id="submit" />
                    </p>
                </form>
                <?php if (isset($message)): ?>
                    <div id="message">
                        <p><?php echo $message; ?></p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>