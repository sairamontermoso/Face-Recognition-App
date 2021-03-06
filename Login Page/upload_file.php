<?php
session_start();
$username = $_SESSION['username'];
$allowedExts = array("mp4", "avi", "mpeg", "mov", "flv", "wmv", "ogg");
$extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
$db = mysqli_connect("localhost", "root", "", "accounts");

# Allowable extensions
if ((($_FILES["file"]["type"] == "video/mp4")       //mp4
     || ($_FILES["file"]["type"] == "video/mpeg")
     || ($_FILES["file"]["type"] == "video/x-flv") // flv file type
     || ($_FILES["file"]["type"] == "video/quicktime") // mov file type
     || ($_FILES["file"]["type"] == "video/x-ms-wmv") // mwv file type
     || ($_FILES["file"]["type"] == "video/ogg")) //ogg
    && ($_FILES["file"]["size"] < 50000000) #50mbs of video space each
    && in_array($extension, $allowedExts))
    {
        $invalidFile = false;
        if ($_FILES["file"]["error"] > 0)
        {
            $uploadSuccessful = false;
        }
        else
        {
            $fileExists = file_exists("videos/" . $username. "/" . $_FILES["file"]["name"]);
            if($fileExists)
            {
                $uploadSuccessful = false;
            }
            else
            {
                $uploadSuccessful = true;
            }
            
        }
    } // End if
else
  {
    $invalidFile = true;
    echo "Invalid Format!";
  }
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Upload Page</title>
    <?php include 'css/css.html'; ?>
</head>
    <body>
        <div class="banner">
            <a href="/">Face Prop</a>
        </div>
        <div class="uploadsection">
            <div class="upload_info">
                <h2 id="upload_header"><u>Upload Information:</u></h2>
                <br/>
                <div class="upload_panel">
                    <p id=upload_text>
                        <?php 
                        if(!$invalidFile)
                        {
                            // If upload is successful and file does not exists, upload
                            if($uploadSuccessful && !$fileExists) 
                            {
                                $username = $_SESSION['username'];
                                $videoname = $_FILES['file']['name'];
                                $type = explode('.', $videoname);
                                $type = end($type);
                                $random_name = rand();
                                
                                // Display the video details to the user
                                echo "Upload: " . $_FILES["file"]["name"] . "<br />";
                                echo "Type: " . $_FILES["file"]["type"] . "<br />";
                                echo "Size: " . ($_FILES["file"]["size"] / 1024) . " Kb<br />";
                                echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br />";
                                
                                // Target location for user folder
                                $target = "videos/".$username.'/'.basename($_FILES['file']['name']);
                                $location = "videos/" . $username . "/";
                                 
                                /* CODE TO INSERT VIDEOS INTO DATABASE AND UPLOAD TO LOCAL FOLDER */
                                //Create directory if it doesn't exist for that user
                                if(!file_exists($location))
                                {
                                    $dir = "videos/". $username;
                                    
                                    // For linux - create directory
                                    if (!is_dir($dir)) {
                                        // Create mask to allow program to create a directory
                                        $oldmask = umask(0);
                                        mkdir($dir, 0777, true);
                                        umask($oldmask);
                                    }
                                   
                                    // Move file to local folder and query to database
                                    move_uploaded_file($_FILES["file"]["tmp_name"], $target);
                                    chmod($target, 0777);
                                    
                                    $uploadToDatabase = "INSERT INTO videos (username, videoName, videoURL) VALUES ('$username', '$videoname', 'videos/$username/$random_name.$type')";
                                    $db->query($uploadToDatabase);
                                }
                                else // Just move appropriate videos to user folder
                                {
                                    move_uploaded_file($_FILES["file"]["tmp_name"], $target);
                                    chmod($target, 0777);
                                    
                                    $uploadToDatabase = "INSERT INTO videos (username, videoName, videoURL) VALUES ('$username', '$videoname', 'videos/$username/$random_name.$type')";
                                    $db->query($uploadToDatabase);
                                }
                                
                                // Get the ID of the video
                                $getID = "SELECT videoID FROM videos WHERE username='$username' AND videoName='$videoname'";
                                $resultID = mysqli_query($db, $getID);
                                while($row = mysqli_fetch_array($resultID)) {
                                    $videoID = $row['videoID'];
                                }
                                
                                /* EXTRACT METADATA FROM VIDEO */
                                $targetPath = __DIR__ . "/" . $target;
                                $metadataScript = "php " . '"' . __DIR__ . "/" . "extract_metadata.php" .   '"' . // The php script
                                    ' "' . $username . '"' . //The username
                                    ' "' . $targetPath . '" ' //The video path
                                    . $videoID; //The video ID
                                exec($metadataScript);
                                
                                // Display metadata info to user
                                $sqlDisplay = "SELECT nframes, fps, Xwidth, Yheight FROM videos WHERE username='$username' AND  videoID='$videoID'";
                                $resultDisplay = mysqli_query($db, $sqlDisplay);
                                while($row = mysqli_fetch_array($resultDisplay))
                                {
                                    $fps = $row['fps'];
                                    $nframes = $row['nframes'];
                                    $width = $row['Xwidth'];
                                    $height = $row['Yheight'];
                                    
                                    // Display the video details to the user
                                    echo "Number of Frames: " . $nframes . "<br />";
                                    echo "Frames Per Second (FPS): " . $fps . "<br />";
                                    echo "Height: " . $height . "px <br />";
                                    echo "Width: " . $width . "px <br />";
                                }

                                
                                echo "Stored in: " . "videos/" . $username.'/'. $_FILES["file"]["name"];
                                
                                // FFMPEG script to extract a thumbnail from the uploaded video
                                
                                /* Commands:
                                    -i Input file name
                                    -an Disabled audio
                                    -ss Get image from X seconds in the video
                                    -s Size of the image
                                    -vf Frame counter
                                */
                                
                                // Create a folder for each video thumbnail
                                $vidNameOnly = explode('.', $videoname); //Get the name of the vid only
                                $vidNamesOnly = $vidNameOnly[0];
                                $vidNameOnly[0] = $vidNameOnly[0] .= " Frames";
                                $thumbnailLocation = "videos/" . $username . "/" . $vidNameOnly[0] . "/";
                                if(!file_exists($thumbnailLocation))
                                {
                                    $thumbnaildir = "videos/". $username . "/" . $vidNameOnly[0];
                                    
                                    // For linux - create directory
                                    if (!is_dir($thumbnaildir)) {
                                        // Create mask to allow program to create a directory
                                        $oldmask = umask(0);
                                        mkdir($thumbnaildir, 0777, true);
                                    }
                                }
                                    
                                $ffmpeg = "ffmpeg";
                                $rawPath = __DIR__ . "/"; // Get the root path where this app is installed
                                $rootPath = str_replace('\\', '/', $rawPath);
                                $videoLoc = $rootPath . "videos/$username/$videoname";
                                $imageFile = "thumbnail%03d.jpg";
                                $size = "120x90";
                                $getFromSecond = 5;
                                $thumbnailPath = $rootPath . "videos/$username/$vidNameOnly[0]/thumbnail";
                                umask($oldmask);
                                
                                // For linux - create directory
                                if (!is_dir($thumbnailPath)) {
                                    // Create mask to allow program to create a directory
                                    $oldmask = umask(0);
                                    mkdir($thumbnailPath, 0777, true);
                                    umask($oldmask);
                                }
                                
                                // Actual command line call
                                $cmd = "$ffmpeg -ss 00:00:01 -i " . '"' .$videoLoc.'"' . " -frames:v 1 -s $size " .'"'."$thumbnailPath"."/$imageFile".'" 2>&1'; 
                               
                                // If ffmpeg shell command exectues
                                if(shell_exec($cmd))
                                {
                                    // Upload the video thumbnail to the database by updating it
                                    $uploadThumbnail = "UPDATE videos SET thumbnail='thumbnail001.jpg' WHERE username='$username' AND videoName='$videoname' AND videoURL='videos/$username/$random_name.$type'";
                                    $db->query($uploadThumbnail);
                                }
                                else
                                {
                                    echo "Error creating thumbnail";
                                }
                                
                                // TODO ADD A CHECK IF CHECKBOX IS CLICKED
                                // Get still images from video using python script
                                $scriptLocation = realpath(__DIR__ . '/../exec/' . "frame_split.py");
                                $pathToVid = __DIR__ . '/videos/' . $username ."/";
                                $saveLocation = __DIR__ . '/videos/' . $username . "/". $vidNameOnly[0] . "/";
                                $script = "/usr/bin/python " //call python
                                    . $scriptLocation . " " //arg[0]
                                    . '"'.$videoname.'"'. " " //arg[1]
                                    . '"'.$videoID.'"'. " " //arg[2]
                                    . '"'.$pathToVid.'"' . " " //arg[3]
                                    . '"'.$saveLocation.'"'; //arg[4]
                                
                                // Allow files to be writable instead of just readable
                                $oldmask = umask(0);
                                $stillScript = exec($script);
                                umask($oldmask);
                                
                                
                                // If user checked the check box, initiate face detection scripts
                                if(isset($_POST['facedetection']))
                                {
                                    // Obtain the 68 data points from the face - programming assignment 4
                                    $faceDetectionScript = "php " . 
                                        "/opt/lampp/htdocs/Face-Recognition-App/Face_Detection/faceDetection.php " . 
                                        $videoID . ' "' . $vidNamesOnly. '" '  . $username;
                                    exec($faceDetectionScript);
                                    echo "<br />" . "Face Detection Script executed succesfully"; 
                                    
                                    // Obtain eye coordinates - programming assignment 5
                                    $eyeDetectionScript = "php /opt/lampp/htdocs/Face-Recognition-App/pupil/eyePupilTracking.php " . $videoID . ' "' . $vidNamesOnly . '" ' . $username;
                                    exec($eyeDetectionScript);
                                    echo "<br />" . "Eye Detection Script executed successfully";
                                    
                                    // Use delauney triangle and create a silent video - programming assignment 6
                                    $videoPath = "/opt/lampp/htdocs/Face-Recognition-App/Login Page/videos/" . $username . "/";
                                    $triangleScript = "php " . "/opt/lampp/htdocs/Face-Recognition-App/delaunay_vid/resultDisplay.php " . $videoID. ' "' . $videoPath . '" ' . '"'.$vidNamesOnly.'"';
                                    
                                    exec($triangleScript);
                                    echo "<br />" . "Delaunay Triangle Script executed successfully";
                                }
    
                            }
                            elseif($fileExists) // If file exists, return error message
                            {
                                echo "Upload: " . $_FILES["file"]["name"] . "<br />";
                                echo "Type: " . $_FILES["file"]["type"] . "<br />";
                                echo "Size: " . ($_FILES["file"]["size"] / 1024) . " Kb<br />";
                                echo "Temp file: " . $_FILES["file"]["tmp_name"] . "<br />";

                                echo $_FILES["file"]["name"] . " already exists. ";
                            }
                            else
                            {
                                echo "Return Code: " . $_FILES["file"]["error"] . "<br />";
                            }
                        }
                        else
                        {
                            echo "Invalid file. Video Format not supported.";
                        }
                       
                        ?>
                    </p>
                    
                </div> <!-- Upload Panel -->
                
            </div> 
            <!-- Redirect user back to profile page after uploading -->
            <h4>Redirecting to profile page in 15 seconds...</h4> 
            <?php
                $db->close(); 
                header( "refresh: 15; url=profile.php" );
            ?>
            <a href="profile.php"><button class="button button-block" name="profile"/>Return to Profile</button></a>
        </div> <!-- Upload Box Form -->
    </body>
    
</html>
