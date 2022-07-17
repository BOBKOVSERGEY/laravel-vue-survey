<?php

namespace App\Http\Controllers;

use App\Http\Resources\SurveyResource;
use App\Models\Survey;
use App\Http\Requests\StoreSurveyRequest;
use App\Http\Requests\UpdateSurveyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SurveyController extends Controller
{

    /**
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $user = $request->user();
        return SurveyResource::collection(
            Survey::where('user_id', $user->id)->paginate()
        );
    }

    /**
     * @param StoreSurveyRequest $request
     * @return SurveyResource
     */
    public function store(StoreSurveyRequest $request)
    {
        $data = $request->validated();

        if(isset($data['image'])) {
            $relativePath = $this->saveImage($data['image']);
            $data['image'] = $relativePath;
        }

        $survey = Survey::create($data);

        return new SurveyResource($survey);
    }


    public function show(Survey $survey, Request $request)
    {
        $user = $request->user();
        if($user->id !== $survey->user_id) {
            return abort(403, 'Unauthorized action');
        }
        return new SurveyResource($survey);
    }

    /**
     * @param UpdateSurveyRequest $request
     * @param Survey $survey
     * @return SurveyResource
     */
    public function update(UpdateSurveyRequest $request, Survey $survey)
    {
        $data = $request->validated();
        // check if image given and save local file system
        if(isset($data['image'])) {
            $relativePath = $this->saveImage($data['image']);
            $data['image'] = $relativePath;

            // if there is an old image, delete it
            if($survey->image) {
                $absolutePath = public_path($survey->image);
                File::delete($absolutePath);
            }

        }
        // Update survey in the database
        $survey->update($data);
        return new SurveyResource($survey);
    }


    public function destroy(Survey $survey, Request $request)
    {
        $user = $request->user();
        if($user->id !== $survey->user_id) {
            return abort(403, 'Unauthorized action');
        }
        $survey->delete();

        // if there is an old image, delete it
        if($survey->image) {
            $absolutePath = public_path($survey->image);
            File::delete($absolutePath);
        }

        return response('', 204);
    }

    private function saveImage($image)
    {
        // Check if image is valid base64 string
        if (preg_match('/^data:image\/(\w+);base64,/', $image, $type)) {
            // Take out the base64 encoded text without mime type
            $image = substr($image, strpos($image, ',') + 1);
            // Get file extension
            $type = strtolower($type[1]); // jpg, png, gif

            // Check if file is an image
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                throw new \Exception('invalid image type');
            }
            $image = str_replace(' ', '+', $image);
            $image = base64_decode($image);

            if ($image === false) {
                throw new \Exception('base64_decode failed');
            }
        } else {
            throw new \Exception('did not match data URI with image data');
        }

        $dir = 'images/';
        $file = Str::random() . '.' . $type;
        $absolutePath = public_path($dir);
        $relativePath = $dir . $file;
        if (!File::exists($absolutePath)) {
            File::makeDirectory($absolutePath, 0755, true);
        }
        file_put_contents($relativePath, $image);

        return $relativePath;
    }
}
