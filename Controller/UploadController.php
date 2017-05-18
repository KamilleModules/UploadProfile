<?php


namespace Controller\UploadProfile;


use Bat\FileSystemTool;
use Core\Services\X;
use Kamille\Architecture\ApplicationParameters\ApplicationParameters;
use Kamille\Architecture\Controller\ControllerInterface;
use Kamille\Architecture\Response\Web\HttpResponse;
use Kamille\Services\XLog;
use Module\UploadProfile\ProfileFinder\ProfileFinderInterface;

class UploadController implements ControllerInterface
{


    public function handleUpload()
    {
        if (
            array_key_exists("file", $_GET) &&
            array_key_exists("file", $_FILES)
        ) {
            $profileId = $_GET['file'];
            $phpFile = $_FILES['file'];

            // AutoAdmin/kamille.ek_product_reference_shop.image

            $finder = X::get("UploadProfile_profileFinder");
            /**
             * @var $finder ProfileFinderInterface
             */
            if (false !== ($profile = $finder->getProfile($profileId))) {


                if (array_key_exists('targetDir', $profile)) {

                    if (array_key_exists('checkUser', $profile) && is_callable($profile['checkUser'])) {
                        if (false === call_user_func($profile['checkUser'])) {
                            $msg = "UploadController.handleUpload: user denied by checkUser callback from profileId $profileId";
                            XLog::info($msg);
                            return $this->errorResponse($msg);
                        }
                    }


                    $newName = $phpFile['name'];
                    if (array_key_exists('getFileName', $profile)) {
                        if (is_callable($profile['getFileName'])) {
                            $newName = call_user_func($profile['getFileName'], $newName);
                        } elseif (is_string($profile['getFileName'])) {
                            $newName = $profile['getFileName'];
                        }
                    }

                    $newFile = $profile['targetDir'] . "/" . $newName;

                    FileSystemTool::mkfile($newFile); // just ensure that directory chain is created

                    if (0 === $phpFile['error']) {

                        $src = $phpFile['tmp_name'];
                        if (is_uploaded_file($src)) {


                            //--------------------------------------------
                            // DO WE ACCEPT THE FILE?
                            //--------------------------------------------
                            if (array_key_exists('accept', $profile)) {
                                $accept = $profile['accept'];
                                if ('image' === $accept && false === $this->isImage($src, $phpFile)) {
                                    $msg = "Upload denied: the given file is not an image: $src, profileId $profileId";
                                    XLog::info($msg);
                                    return $this->errorResponse($msg);
                                } elseif (is_callable($accept) && false === call_user_func($accept, $src)) {
                                    $msg = "Upload denied: the callback returned false for file $src, profileId $profileId";
                                    XLog::info($msg);
                                    return $this->errorResponse($msg);
                                }
                            }


                            //--------------------------------------------
                            // UPLOAD THE FILE
                            //--------------------------------------------
                            if (true === move_uploaded_file($src, $newFile)) {


                                if (true === ApplicationParameters::get("debug")) {
                                    XLog::log("debug.upload", "File uploaded: $newFile");
                                }

                                if (array_key_exists('uploadAfter', $profile) && is_callable($profile['uploadAfter'])) {
                                    call_user_func($profile['uploadAfter'], $newFile);
                                }


                                //--------------------------------------------
                                // SEND THE RESPONSE BACK
                                //--------------------------------------------
                                $fileUri = $newFile;
                                if (array_key_exists('getUri', $profile)) {
                                    if (is_callable($profile['getUri'])) {
                                        $fileUri = call_user_func($profile['getUri'], $newName);
                                    } elseif (is_string($profile['getUri'])) {
                                        $fileUri = $profile['getUri'];
                                    }
                                } else {
                                    $wwwDir = ApplicationParameters::get("app_dir") . "/www";
                                    if (0 === strpos($fileUri, $wwwDir)) {
                                        $fileUri = str_replace($wwwDir, '', $fileUri);
                                    }
                                }
                                return HttpResponse::create($fileUri, 200);

                            } else {
                                XLog::error("UploadController.handleUpload: move_uploaded_file function failed with profileId $profileId");
                            }
                        }
                    } else {
                        $err = $phpFile['error'];
                        XLog::warn("UploadController.handleUpload: a php error occurred: $err, while uploading file using profileId $profileId");
                    }

                } else {
                    XLog::error("UploadController.handleUpload: targetDir key not found with profileId $profileId");
                }
            }
        }
        return $this->errorResponse("Some error occurred, check your logs");
    }

    //--------------------------------------------
    //
    //--------------------------------------------
    protected function isImage($path, array $phpFile)
    {
        if (in_array($phpFile['type'], ['image/jpeg', 'image/gif', 'image/png'])) {
            return true;
        }
        return false;
    }
    //--------------------------------------------
    //
    //--------------------------------------------
    private function errorResponse($msg)
    {
        return HttpResponse::create("UploadController.handleUpload error: " . $msg);
    }

}