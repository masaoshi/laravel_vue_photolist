<?php

namespace Tests\Feature;

use App\Photo;
use App\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PhotoSubmitApiTest extends TestCase
{
    use RefreshDatabase;

    public function setUp()
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    /**
     * @test
     */
    public function should_ファイルをアップロードできる()
    {
        // // テスト用のストレージ storage/framework/testing
        // S3は使用せず、ローカルに保存する為コメントアウト
        // Storage::fake('s3');

        $response = $this->actingAs($this->user)
            ->json('POST', route('photo.create'), [
                // ダミーファイルを作成して送信
                'photo' => UploadedFile::fake()->image('photo.jpg'), 
            ]);
        // dd($response);
        // レスポンス
        $response->assertStatus(201);

        $photo = Photo::first();

        // 写真のIDが12桁のランダムな文字列であること
        $this->assertRegExp('/^[0-9a-zA-Z-_]{12}$/', $photo->id);

        // DBに挿入されたファイル名のファイルがストレージに保存されていること
        // S3は使用せず、ローカルに保存する為コメントアウト
        // Storage::cloud()->assertExists($photo->filename);

        Storage::disk('public')->assertExists($photo->filename);
        // テストで使用したファイルを削除
        Storage::disk('public')->delete($photo->filename);
    }

    /**
     * @test
     */
    public function should_データベースエラーの場合はファイルを保存しない()
    {
        // 乱暴だがこれでDBエラーを起こす
        Schema::drop('photos');

        // S3は使用せず、ローカルに保存する為コメントアウト
        // Storage::fake('s3');

        $response = $this->actingAs($this->user)
            ->json('POST', route('photo.create'), [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);
        
        // レスポンスが500(INTERNAL SERVER ERROR)であること
        $response->assertStatus(500);

        // ストレージにファイルが保存されていないこと
        // S3は使用せず、ローカルに保存する為コメントアウト
        // $this->assertEquals(0, count(Storage::cloud()->files()));

        // .gitignoreが存在する為1カウント
        $this->assertEquals(1, count(Storage::disk('public')->files()));
    }

    /**
     * @test
     */
    public function should_ファイル保存エラーの場合はDBへの挿入はしない()
    {
        // // ストレージをモックして保存時にエラーを起こさせる
        // // Storage::shouldReceive('could')
        Storage::shouldReceive('disk')
            ->with('public')
            ->once()
            ->andReturnNull();

        $response = $this->actingAs($this->user)
            ->json('POST', route('photo.create'), [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
            ]);

        // レスポンスが500(INTER SERVER ERROR)であること
        $response->assertStatus(500);

        // データベースに何も挿入されていないこと
        $this->assertEmpty(Photo::all());
    }
}
