<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class ImageService
{
    /**
     * Upload an image to the specified directory.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @return string The public path of the uploaded image.
     */
    public static function upload($file, $directory)
    {
        $uniqueName = uniqid() . '.' . $file->getClientOriginalExtension();
        $destinationPath = public_path($directory);

        // Ensure the directory exists
        if (!File::exists($destinationPath)) {
            File::makeDirectory($destinationPath, 0755, true);
        }

        // Move the file to the public directory
        $file->move($destinationPath, $uniqueName);

        return $directory . '/' . $uniqueName;
    }

    /**
     * Update an existing image by replacing it with a new one.
     *
     * @param \Illuminate\Http\UploadedFile $newFile
     * @param string|null $existingPath
     * @param string $directory
     * @return string The public path of the updated image.
     */
    public static function update($newFile, $existingPath, $directory)
    {
        // Delete the existing file if it exists
        if ($existingPath && File::exists(public_path($existingPath))) {
            File::delete(public_path($existingPath));
        }

        return self::upload($newFile, $directory);
    }

    /**
     * Get the public URL of an image.
     *
     * @param string|null $path
     * @return string|null
     */
    public static function get($path)
    {
        return $path ? asset($path) : null;
    }

    /**
     * Delete an image from the storage.
     *
     * @param string|null $path
     * @return bool
     */
    public static function delete($path)
    {
        if ($path && File::exists(public_path($path))) {
            return File::delete(public_path($path));
        }

        return false;
    }
}
