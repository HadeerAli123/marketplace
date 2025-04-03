<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\ContactUs;
use Illuminate\Http\Request;

class ContactUsController extends Controller
{
    public function index()
    {
        try {
            $messages = ContactUs::all();
            return response()->json(['data' => $messages], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string|min:9',
                'name' => 'required|string|max:255',
                'message' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }

            $message = new ContactUs();
            $message->phone = $request->phone;
            $message->name = $request->name;
            $message->message = $request->message;
            $message->save();

            return response()->json(['message' => 'Saved successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(String $id)
    {
        try {
            $message = ContactUs::find($id);
            if (!$message) {
                return response()->json(['error' => 'Message not found'], 404);
            }
            $message->delete();
            return response()->json(['message' => 'Deleted successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
