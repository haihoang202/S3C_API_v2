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
use Aws\S3\S3Client;


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

        $document = Documents::where(['user_id'=>$user_repo,'id_name'=>$file_to_decrypt])->get();

        $path = storage_path("app/{$user_repo}/{$document[0]->id_name}");

        $s3_access_key = $data['s3_access_key'];
        $s3_secret_key = $data['s3_secret_key'];
        $s3_bucket = $data['s3_bucket'];
        $s3_region_name = $data['s3_region_name'];
        $s3_key = $data['s3_key'];

        $s3 = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => $s3_region_name,
            'credentials' => [
                'key'    => $s3_access_key,
                'secret' => $s3_secret_key,
            ],
        ]);

        $result = $s3->getObject(array(
            'Bucket' => $s3_bucket,
            'Key'    => $s3_key."/".$file_to_decrypt,
            'Delimiter'=>'/',
            'SaveAs' => $path,
        ));

        $configPath = storage_path("app/{$user_repo}/config.properties");

        $cmd = "java -jar S3Client.jar -d {$path} {$configPath}";

        exec($cmd, $output, $return);

        return response()->download($path);

    }

    public function edit(Request $request) {
        $user = Auth::user();

    }

    public function remove(Request $request) {
        $user = Auth::user();
        $data = $request->all();
        $file_to_remove = $data['file_to_remove'];
        $user_repo = $user->id;

        //Remove in database
        $document = Documents::where(['user_id'=>$user_repo,'id_name'=>$file_to_remove])->delete();

        $path_temp = storage_path("app/{$user_repo}/{$file_to_remove}");
        $configPath = storage_path("app/{$user_repo}/config.properties");

        //Remove from S3 Index and DocSize
        $cmd = "java -jar S3Client.jar -r {$path_temp} {$configPath}";
        exec($cmd, $output, $return);

        //Remove from API storage
        $file_root = str_replace("txt","",$file_to_remove);
        $cmd = "rm -rf {$file_root}.*";
        exec($cmd, $output, $return);

        $s3_access_key = $data['s3_access_key'];
        $s3_secret_key = $data['s3_secret_key'];
        $s3_bucket = $data['s3_bucket'];
        $s3_region_name = $data['s3_region_name'];
        $s3_key = $data['s3_key'];

        $s3 = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => $s3_region_name,
            'credentials' => [
                'key'    => $s3_access_key,
                'secret' => $s3_secret_key,
            ],
        ]);

        $result = $s3->deleteObject(array(
            'Bucket' => $s3_bucket,
            'Key'    => $s3_key."/".$file_to_remove,
            'Delimiter'=>'/',
        ));

        $result .= $s3->deleteObject(array(
            'Bucket' => $s3_bucket,
            'Key'    => $s3_key."/".str_replace('.txt','.key',$file_to_remove),
            'Delimiter'=>'/',
        ));

        return response()->json(['result'=>"Deleted file {$file_to_remove}", 'query result'=>$result]);

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
        //Authenticate user
        $user = Auth::user();

        $user_repo = $user->id;
        $filename = $request->file('file')->getClientOriginalName();
        $file = $request->file('file')->store($user_repo);

        $path = storage_path("app/{$file}");
        $path_temp = public_path('upload');
        $configPath = storage_path("app/{$user_repo}/config.properties");
        $user_path = storage_path("app/{$user_repo}");

        $name = str_replace_first("{$user_repo}/","",$file);

        //Create record in the database
        $file_record = Documents::create([
            'name' => $filename,
            'user_id' => $user_repo,
            'path' => $path,
            'id_name' => $name,
        ]);

        //Copy file from user repo to upload folder for processing
        $cmd = "cp {$path} {$path_temp}";
        exec($cmd, $output, $return);
        $cmd = "java -jar S3Client.jar -u {$path_temp} {$configPath}";
        exec($cmd, $output, $return);

        //Transfer data to user's S3 bucket
        $data = $request->all();
        $s3_access_key = $data['s3_access_key'];
        $s3_secret_key = $data['s3_secret_key'];
        $s3_bucket = $data['s3_bucket'];
        $s3_region_name = $data['s3_region_name'];

        $s3 = new \Aws\S3\S3Client([
            'version'     => 'latest',
            'region'      => $s3_region_name,
            'credentials' => [
                'key'    => $s3_access_key,
                'secret' => $s3_secret_key,
            ],
        ]);

        $dest = 's3://'.$s3_bucket;
        $manager = new \Aws\S3\Transfer($s3, $path_temp, $dest);

        $manager->transfer();

        $cmd = "mv {$path_temp}/*.* {$user_path}";

        exec($cmd, $output1, $return);
        return response()->json(['result'=>'Done'],200);
//        return response()->download($path);
    }

}
