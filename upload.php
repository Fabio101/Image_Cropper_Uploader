<?php
/**
*
* Author : Fabio Pinto - 50121308
*
* Description : handles the file upload and resize operations for the image cropper app.
*
**/

require_once("git.php");
require_once("crop.php");
require_once("sqlite.php");

$target_dir = "../client_data/". $_POST["company_login"] . "/";
$target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
$uploadOk = 1;
$imageFileType = pathinfo($target_file,PATHINFO_EXTENSION);
$curdir = getcwd();

// Check is target dirctory exists
if (!file_exists ( $target_dir )) {
    echo "Company Login name does not exist. Please try again.";
    header("HTTP/1.1 500 Company name does not exist! ");
    $uploadOk = 0;
    return;
}

// Ensure Git repo is up to date...
$GIT = new git();
if ($GIT->pull($target_dir) !=0) {
    $uploadOk= 0;
    echo "PHP was unable to succesfully run the git pull command! Contact support@parallel.co.za!";
    header("HTTP/1.1 500 Git pull failed! ");
    return;
}
// CHange back to the original working directory
chdir($curdir);

// Check if image file is a actual image or fake image
if(isset($_POST["submit"])) {
    $check = getimagesize($_FILES["fileToUpload"]["tmp_name"]);
    if($check !== false) {
        echo "File is an image - " . $check["mime"] . ".";
        $uploadOk = 1;
    } else {
        echo "File is not an image.";
        $uploadOk = 0;
        return;
    }
}

// Check file size
if ($_FILES["fileToUpload"]["size"] > 500000) {
    echo "Sorry, your file is too large. ";
    header("HTTP/1.1 500 File is too large! ");
    $uploadOk = 0;
    return;
}

// Allow certain file formats
if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
    echo "Sorry, only JPG, JPEG, PNG & GIF files are allowed. ";
    header("HTTP/1.1 500 Invalid file format! ");
    $uploadOk = 0;
    return;
}else {
    // Determine file extension
    $ext = end((explode(".", $target_file)));
}

// Check if $uploadOk is set to 0 by an error
if ($uploadOk == 0) {
    echo "Something went wrong! Contact support@parallel.co.za";
    header("HTTP/1.1 500 An error occured whilst trying upload your image! ");
    return;
    
// if everything is ok, try to upload, resize and replace the file...
} else {
    if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) {
        
        // Get new sizes
        list($width, $height) = getimagesize($target_file);
        $newx = $_POST["x"];
        $newy = $_POST["y"];
        $newwidth = $_POST["width"];
        $newheight = $_POST["height"];
        
        //Begin resize operation and replacements...
        cropImage($newx, $newy, $newwidth, $newheight, $width, $height, $target_dir, $target_file, $GIT, $curdir);
    
        echo "The file ". basename( $_FILES["fileToUpload"]["name"]). " has been uploaded.";
        header("HTTP/1.1 200 OK");
    } else {
        echo "Sorry, there was an error uploading your file, Please contact support@parallel.co.za.";
        header("HTTP/1.1 500 An error occured whilst trying to upload your image!");
        return;
    }
}

function cropImage($x, $y, $neww, $newh, $w, $h, $dir, $file, $GIT, $curdir) {
    
    // Instantiate crop class
    $CROP = new cropper();
    
    // Recheck extention for below conditional
    $ext2 = end((explode(".", $file)));
    
    if ($ext2 == "jpg") {
        // We use Imagick to crop the file and write to a new jpg
        $outFile = $dir . "cropped.jpg";
        // Call the crop method to crop the image...
        $CROP->edit($outFile, $neww, $newh, $x, $y, $file);
        
    } else {
        $outFile = $dir . "cropped.png";
        $CROP->edit($outFile, $neww, $newh, $x, $y, $file);
    }
    replace($dir, $ext2, $file, $outFile, $GIT, $curdir);
}

function replace($d, $e, $f, $of, $GIT, $curdir) {
    // Now we replace the old image with the new one
    if (file_exists ( $d . "comapny." . $e )) {
        unlink($d. "company." . $e);    
    }
    unlink($f);
    rename($of, $d . "company." . $e);
    
    // Now we can commit our new file to the repo
    if ($GIT->add($d) != 0) {
        $uploadOk= 0;
        echo "PHP was unable to succesfully run the git add! Contact support@parallel.co.za!";
        header("HTTP/1.1 500 An error occured whilst trying to track your image!");
        return;
    }
    chdir($curdir);
    
    // Then we commit with a message...
    if ($GIT->commit($d, $_POST["company_login"]) != 0) {
        $uploadOk= 0;
        echo "PHP was unable to succesfully run the git commit! Contact support@parallel.co.za!";
        header("HTTP/1.1 500 An error occured whilst trying to commit your image!");
        return;
    }
    chdir($curdir);
    // Now we push to our test branch
    if ($GIT->push($d) != 0) {
        $uploadOk= 0;
        echo "PHP was unable to succesfully run the git push! Contact support@parallel.co.za!";
        header("HTTP/1.1 500 An error occured whilst trying to push your image!");
        return;
    }
    chdir($curdir);

    // Now we can pull on our PP server
    $db = new MyDB();

    if(!$db){
        echo $db->lastErrorMsg();
        echo "PHP was unable to succesfully connect to SQLite! Contact support@parallel.co.za!";
        header("HTTP/1.1 500 An error occured whilst trying to connect to SQLite!");
        return;
    } else {
      echo "OK";
    }

        $sql = 'SELECT * from creds WHERE ID = 1;';

        $ret = $db->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC)){
            $host = $row['HOST'];
            $user = $row['USER'];
            $pass = $row['PASS'];
        }
    chdir($curdir);

var_dump($GIT->remotePull($host, $user, $pass));
die();


    $GIT->remotePull($host, $user, $pass);
        #$uploadOk= 0;
        #echo "PHP was unable to succesfully run the git pull on remote host! Contact support@parallel.co.za!";
        #header("HTTP/1.1 500 An error occured whilst trying to push your image!");
        #return;
    #}
}
?>
