<hr />
<h1>DEV MODE IS ON -- Do not use in production!</h1>

<?php if (count($GLOBALS['messages']) > 0): ?>
    <?php if (!isTest()): ?>
        <h4>status</h4>
        <?php $feedback = display_feedback($GLOBALS['messages']); ?>
        <?php if ($feedback): ?>
            <?php display_feedback($GLOBALS['messages']); ?>
        <?php else: ?>
            Status not available</br>
        <?php endif; ?>
    <?php endif; ?>
<?php else: ?>
    Status not available</br>
<?php endif; ?>


<?php if (!isTest()): ?>
    <h4>logged in</h4>
    <?php if (loggedIn): ?>
        <?php echo isset($GLOBALS['user_data']['username']) ? $GLOBALS['user_data']['username'] : "Username not set</br>"; ?>
    <?php else: ?>
        Not logged in</br>
    <?php endif; ?>
<?php endif; ?>


<h4>url_loc</h4>
<?php print_r($GLOBALS['url_loc']); ?>

<h4>token</h4>

<?php 
if($isLoggedIn){
    if (isset($GLOBALS['logged_in']) && $GLOBALS['logged_in'] === false) {
        // If logged_in is false, show the dev token if available 
        if(DEVMODE === true && loggedIn === true){
      !defined(DEV_MODE_TOKEN) ? (!empty (DEV_MODE_TOKEN) ? print_r(DEV_MODE_TOKEN) : print "Token is empty</br>") : print "Token not available</br>";
        } else {
            print "'Logged In' from `system_constants.php` is not true.</br>";
        }
    } else {
        print_r($GLOBALS['token']);
    }
} else {
    print "Not logged in</br>";
}
?>

<h4>user_id</h4>
<?php echo isset ($GLOBALS['user_id']) ? $GLOBALS['user_id'] : "UserId not available</br>"; ?>