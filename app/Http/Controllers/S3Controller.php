<?php

namespace App\Http\Controllers;

use Couchbase\Document;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Documents;
use GuzzleHttp\Client;

class S3Controller extends Controller
{

    public function listFiles() {
        $user = Auth::user();

        $documents = DB::table('documents')->where('user_id',$user->id)->get();

        return response()->json(['result'=>$documents],200);

    }

    public function download(Request $request) {
        $user = Auth::user();
        $data = $request->all();
        $file = $data['name'];
        $user_repo = $user->id;

        $document = Documents::where(['user_id'=>$user_repo,'name'=>$file])->get();

        $path = $document[0]->path;

        return response()->download($path);
    }

    public function download_decrypt(Request $request) {
        $user = Auth::user();
        $data = $request->all();
        $file_to_decrypt = $data['file_to_decrypt'];
        $user_repo = $user->id;

        $document = Documents::where(['user_id'=>$user_repo,'name'=>$file_to_decrypt])->get();

        $path = storage_path("app/{$user_repo}/{$document[0]->id_name}");
        $configPath = storage_path("app/{$user_repo}/config.properties");

        $cmd = "java -jar S3Client.jar -d {$path} {$configPath}";

        exec($cmd, $output, $return);

        return response()->download($path);

    }

    public function edit() {
        $user = Auth::user();

    }

    public function remove(Request $request) {
        $user = Auth::user();
        $data = $request->all();
        $file_to_remove = $data['file_to_remove'];
        $user_repo = $user->id;

        $document = Documents::where(['user_id'=>$user_repo,'id_name'=>$file_to_remove])->delete();

        $path_temp = public_path('upload');
        $configPath = storage_path("app/{$user_repo}/config.properties");

        $cmd = "java -jar S3Client.jar -u {$path_temp} {$configPath}";

        exec($cmd, $output, $return);

        return response()->json(['result'=>"Deleted file {$file_to_remove}"]);

    }

    public function search(Request $request) {
        $user = Auth::user();

        $user_repo = $user->id;

        $data = $request->all();

        $query = $data['query'];

        $opt = $data['option'];

        $configPath = storage_path("app/{$user_repo}/config.properties");

        $cmd = "java -jar S3Client.jar -s \"{$query}\" {$opt} {$configPath}";

        exec($cmd, $output, $return);

        return response()->json(['result'=>$output]);
    }

    public function upload(Request $request) {
//        $user = Auth::user();
//
//        $user_repo = $user->id;
//
//        $filename = $request->file('file')->getClientOriginalName();
//
//        $file = $request->file('file')->store($user_repo);
//
//
//        $path = storage_path("app/{$file}");
//        $path_temp = public_path('upload');
//        $configPath = storage_path("app/{$user_repo}/config.properties");
//        $user_path = storage_path("app/{$user_repo}");
//
//        $name = str_replace_first("{$user_repo}/","",$file);
//
//        $file_record = Documents::create([
//            'name' => $filename,
//            'user_id' => $user_repo,
//            'path' => $path,
//            'id_name' => $name,
//        ]);
//
//        $cmd = "cp {$path} {$path_temp}";
//
//        exec($cmd, $output, $return);
//
//        $cmd = "java -jar S3Client.jar -u {$path_temp} {$configPath}";
//
////        dd($cmd);
//
//        exec($cmd, $output, $return);
//
//        $cmd = "mv {$path_temp}/*.* {$user_path}";
//
//        exec($cmd, $output1, $return);
//
////        return response()->json(['result'=>$output,'name'=>$file_record['name'],'created_at'=>$file_record['created_at']],200);
//        return response()->json(['result'=>$output,'success'=>$file_record],200);


        $user = Auth::user();
        $data = $request->all();
        $filename = $data['filename'];
        $s3_access_key = $data['s3_access_key'];
//        $s3_secret_key = $data['s3_secret_key'];
        $s3_service = $data['s3_service'];
        $s3_region_name = $data['s3_region_name'];

        $host = $s3_service . "s3.amazonaws.com";

        $client = new Client([
           'base_uri' => $host,
            'timeout' => 2.0,
        ]);

        $date = date("Y-m-d h:i:s");
        $cmd = "python signature.py -k {$s3_access_key} -d \"{$date}\" -r {$s3_region_name} -s {$s3_service}";
        exec($cmd, $output, $return);

//        print($output);
//        dd($cmd);

        $response = $client->request("PUT","/{$filename}", ['auth' => "{$output}"]);


    }

}
