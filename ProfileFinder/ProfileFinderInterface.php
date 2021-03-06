<?php


namespace Module\UploadProfile\ProfileFinder;


interface ProfileFinderInterface
{
    /**
     * @return array|false
     *
     *
     * Note: some of the the profiles information are optional, those are a suggestion of how upload
     * should behave, but the implementor has the choice to not provide the desired feature.
     *
     *
     *
     *
     * The returned profile is an array with the following entries:
     *
     *
     * - targetDir: the full path to the directory containing the items
     * - ?maxFileSize: int, size in M
     * - ?accept: array of accepted mime types
     * - ?acceptExtensions: array of accepted extensions
     *
     *
     */
    public function getProfile($profileId);
}