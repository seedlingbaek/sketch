<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

use App\Models\Book;
use App\Models\Thread;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Label;
use App\Models\Tongren;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StoreBook extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'title' => 'required|string|max:20',
            'brief' => 'required|string|max:25',
            'wenan' => 'max:2000',
            'channel_id' => 'required|numeric|min:1|max:2',
            'label_id' => 'required|numeric|min:1',
            'book_status' => 'required|numeric|min:1|max:3',
            'book_length' => 'required|numeric|min:1|max:3',
            'sexual_orientation' => 'required|numeric|min:1|max:7',
            'bianyuan' =>'required',
            'majia' => 'string|max:10',
        ];
    }

    public function tags_validate($tags,$bianyuan,$channel_id)
    {
        if (count($tags)>3) {
            return false;
        }
        if(!$bianyuan){
            if($tags){
                foreach ($tags as $tag){
                    if(\App\Models\Tag::find($tag)->tag_group==5){
                        return false;
                    }
                }
            }
        }
        if($channel_id===1){//检查同人tag使用情况，如果是原创，不准使用同人tag（tag_group>5）
            if($tags){
                foreach ($tags as $tag){
                    if(\App\Models\Tag::find($tag)->tag_group>5){
                        return false;
                    }
                }
            }
        }
        return true;
    }

    public function generateBook()
    {
        $book_data = $this->only('book_status','book_length','sexual_orientation');
        $thread_data = $this->only('channel_id','label_id','title','brief');
        if ($this->anonymous){
            $thread_data['anonymous']=1;
            $thread_data['majia']=$this->majia;
            auth()->user()->update(['majia'=>$this->majia]);
        }else{
            $thread_data['anonymous']=0;
        }
        $thread_data['body']=$this->wenan;
        $thread_data['lastresponded_at']=Carbon::now();
        $thread_data['user_id'] = auth()->id();
        $thread_data['bianyuan'] = $this->bianyuan=='1'? 1:0;
        $thread_data['public']=$this->public ? true:false;
        $thread_data['noreply']=$this->noreply ? true:false;
        $thread_data['download_as_book']=$this->download_as_book ? true:false;
        $thread_data['download_as_thread']=$this->download_as_thread ? true:false;

        //post_data
        $post_data = [];
        $post_data['user_ip'] = $this->getClientIp();
        $post_data['user_id'] = auth()->id();
        $post_data['markdown']=$this->markdown ? true:false;
        $post_data['indentation']=$this->indentation ? true:false;
        //tags_data
        $tags_data = $this->tags;
        //tongren_data

        $tongren_data = $this->only('tongren_yuanzhu','tongren_cp','tongren_yuanzhu_tag_id','tongren_CP_tag_id');
        $tongren_data = $this->tongren_data_sync($tongren_data);

        //查看标签是否符合权限
        if(Label::find($thread_data['label_id'])->channel_id!=(int)$thread_data['channel_id']){
            abort(403,'数据冲突');
        }

        //查看tag数目是否符合要求，是否存在边缘tag但是没注明
        if (!$this->tags_validate($tags_data,$thread_data['bianyuan'],$thread_data['channel_id'])){
            abort(403,'数据冲突');
        }

        if (!$this->isDuplicateBook($thread_data)){
            $thread = DB::transaction(function () use($book_data,$thread_data,$post_data,$tags_data,$tongren_data) {
                $book = Book::create($book_data);
                $thread_data['book_id'] = $book->id;
                $thread = Thread::create($thread_data);
                $thread->update_channel();
                $book->update(['thread_id'=>$thread->id]);
                $post_data['thread_id'] = $thread->id;
                $post = Post::create($post_data);
                $thread->update(['post_id'=>$post->id]);
                $thread->tags()->sync($tags_data);
                if ($thread->channel_id == 2){
                    $tongren_data['book_id']=$book->id;
                    $tongren = Tongren::create($tongren_data);
                }
                return $thread;
            }, 2);
        }else{
            abort(400,'请求已登记，请勿重复提交相同数据');
        }
        //tags, tongren
        return $thread;
    }

    public function isDuplicateBook($data)
    {
        $last_book = Thread::where('user_id', auth()->id())
                            ->orderBy('id', 'desc')
                            ->first();
        return count($last_book) && strcmp($last_book->title.$last_book->brief.$last_book->body, $data['title'].$data['brief'].$data['body']) === 0;
    }

    public function tongren_data_sync($data)
    {
        if(((array_key_exists('tongren_yuanzhu_tag_id',  $data)))&&($data['tongren_yuanzhu_tag_id']>0)){
            $data['tongren_yuanzhu']=Tag::find($data['tongren_yuanzhu_tag_id'])->tag_explanation;
        }
        if(((array_key_exists('tongren_cp_tag_id', $data)))&&($data['tongren_cp_tag_id']>0)){
            $data['tongren_cp']=Tag::find($data['tongren_CP_tag_id'])->tag_explanation;
        }
        return $data;
    }

    public function updateBook(Thread $thread)
    {
        $book = $thread->book;
        //book_data
        $book_data = $this->only('book_status','book_length','sexual_orientation');
        //thread_data
        $thread_data = $this->only('label_id','title','brief');
        if ($this->anonymous){
            $thread_data['anonymous']=1;
        }else{
            $thread_data['anonymous']=0;
        }
        $thread_data['body']=$this->wenan;
        $thread_data['edited_at']=Carbon::now();
        $thread_data['bianyuan'] = $this->bianyuan=='1'? true:false;
        $thread_data['public']=$this->public ? true:false;
        $thread_data['noreply']=$this->noreply ? true:false;
        $thread_data['download_as_book']=$this->download_as_book ? true:false;
        $thread_data['download_as_thread']=$this->download_as_thread ? true:false;

        //post_data
        $post_data = [];
        $post_data['markdown']=$this->markdown ? true:false;
        $post_data['indentation']=$this->indentation ? true:false;
        //tags_data
        $tags_data = $this->tags;
        //tongren_data
        $tongren_data = $this->only('tongren_yuanzhu','tongren_cp','tongren_yuanzhu_tag_id','tongren_CP_tag_id');
        $tongren_data = $this->tongren_data_sync($tongren_data);

        //查看标签是否符合权限
        if(\App\Models\Label::find($thread_data['label_id'])->channel_id!=$thread->channel_id){
            abort(403,'数据冲突');
        }

        //查看tag数目是否符合要求，是否存在边缘tag但是没注明
        if (!$this->tags_validate($tags_data,$thread_data['bianyuan'],$thread->channel_id)){
            abort(403,'数据冲突');
        }

        $book->update($book_data);
        $thread->update($thread_data);
        $thread->mainpost->update($post_data);
        if($thread->channel_id==2){
            $book->tongren->update($tongren_data);
        }
        $thread->tags()->sync($tags_data);

        return $thread;
    }
}
