<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;
use App\Models\Quote;
use App\Models\Channel;
use App\Models\Label;
use App\Models\Tag;
use App\Models\Thread;
use App\Models\Post;
use App\Models\User;
use App\Models\PostComment;
use App\Models\Administration;
use App\Models\Book;
use App\Models\Message;
use Auth;
use Carbon\Carbon;

class AdminsController extends Controller
{
    //所有这些都需要用transaction，以后再说
   public function __construct()
   {
     $this->middleware('admin');
   }
   public function index()
   {
      return view('admin.index');
   }
   public function quotesreview()
   {
      $quotes = Quote::orderBy('created_at', 'desc')->paginate(config('constants.index_per_page'));
      return view('admin.quotesreview', compact('quotes'));
   }

   public function toggle_review(Quote $quote)
   {
      $quote->approved = !$quote->approved;
      $quote->reviewed = true;
      $quote->update();
      return $quote;
   }

   public function threadmanagement(Thread $thread, Request $request)
   {
      $this->validate($request, [
          'reason' => 'required|string',
      ]);
      $var = request('controlthread');
      if ($var=="1"){
         $thread->locked = !$thread->locked;
         $thread->save();
         if($thread->locked){
            Administration::create([
               'user_id' => Auth::id(),
               'operation' => '1',//1:锁帖
               'item_id' => $thread->id,
               'reason' => request('reason'),
            ]);
         }else{
            Administration::create([
               'user_id' => Auth::id(),
               'operation' => '2',//1:解锁
               'item_id' => $thread->id,
               'reason' => request('reason'),
            ]);
         }
         return redirect()->back()->with("success","已经成功处理该主题");
      }
      if ($var=="2"){
         $thread->public = !$thread->public;
         $thread->save();
         if(!$thread->public){
            Administration::create([
               'user_id' => Auth::id(),
               'operation' => '3',//3:转为私密
               'item_id' => $thread->id,
               'reason' => request('reason'),
            ]);
         }else{
            Administration::create([
               'user_id' => Auth::id(),
               'operation' => '4',//4:转为公开
               'item_id' => $thread->id,
               'reason' => request('reason'),
            ]);
         }
         return redirect()->back()->with("success","已经成功处理该主题");
      }
      if ($var=="3"){
         Administration::create([
            'user_id' => Auth::id(),
            'operation' => '5',//5:删帖
            'item_id' => $thread->id,
            'reason' => request('reason'),
         ]);
         $thread->delete();
         return redirect()->route('home')->with("success","已经删帖");
      }
      if ($var=="4"){//书本/主题贴转移版块

        DB::transaction(function () use($thread){
            Administration::create([
               'user_id' => Auth::id(),
               'operation' => '9',//转移版块
               'item_id' => $thread->id,
               'reason' => request('reason'),
            ]);
            $label = Label::findOrFail(request('label'));
            $channel = Channel::findOrFail(request('channel'));
            if(($label)&&($label->channel_id == $channel->id)){
               $thread->channel_id = $channel->id;
               $thread->label_id = $label->id;
               if($channel->channel_state!=1){
                   $thread->book->delete();
                   $thread->book_id = 0;
               }else{
                  if($thread->book_id==0){//这篇主题本来并不算文章,新建文章
                     $book = Book::create([
                        'thread_id' => $thread->id,
                        'book_status' => 0,
                        'book_length' => 0,
                        'lastaddedchapter_at' => Carbon::now(),
                     ]);
                     $tongren = App\Models\Tongren::create(
                         ['book_id' => $book->id]
                     );
                  }else{
                     $book = Book::findOrFail($thread->book_id);
                     $book->save();
                     if($channel->id == 2){
                        $tongren = \App\Models\Tongren::firstOrCreate(['book_id' => $book->id]);
                     }
                  }
               }
            }
        });

         $thread->save();
         return redirect()->route('thread.show', $thread)->with("success","已经转移操作");
      }
      return redirect()->back()->with("danger","请选择操作类型（转换板块？）");
   }
   public function postmanagement(Post $post, Request $request)
   {
     $this->validate($request, [
         'reason' => 'required|string',
         'majia' => 'required|string|max:10'
     ]);
     $var = request('controlpost');//
     if ($var=="7"){//删帖
        Administration::create([
          'user_id' => Auth::id(),
          'operation' => '7',//:删回帖
          'item_id' => $post->id,
          'reason' => request('reason'),
        ]);
        if($post->chapter_id !=0){
          App\Models\Chapter::destroy($post->chapter_id);
        }
        $post->delete();
        return redirect()->back()->with("success","已经成功处理该贴");
     }
     if ($var=="10"){//修改马甲
       if (request('anonymous')=="1"){
         $post->anonymous = true;
         $post->majia = request('majia');
       }
       if (request('anonymous')=="2"){
         $post->anonymous = false;
       }
       $post->save();
        Administration::create([
          'user_id' => Auth::id(),
          'operation' => '10',//:修改马甲
          'item_id' => $post->id,
          'reason' => request('reason'),
        ]);
        return redirect()->back()->with("success","已经成功处理该回帖");
     }
     if ($var=="11"){//折叠
       $post->fold_state = !$post->fold_state;
       $post->save();
        Administration::create([
           'user_id' => Auth::id(),
           'operation' => ($post->fold_state? '11':'12'),//11 => '折叠帖子',12 => '解折帖子'
           'item_id' => $post->id,
           'reason' => request('reason'),
        ]);
        return redirect()->back()->with("success","已经成功处理该回帖");
     }
     return redirect()->back()->with("warning","什么都没做");
   }
   public function postcommentmanagement(PostComment $postcomment, Request $request)
   {
      $this->validate($request, [
          'reason' => 'required|string',
      ]);
      if(request("delete")){
         Administration::create([
            'user_id' => Auth::id(),
            'operation' => '8',//:删回帖
            'item_id' => $postcomment->id,
            'reason' => request('reason'),
         ]);
         $postcomment->delete();
         return redirect()->back()->with("success","已经成功处理该点评");
      }
      return redirect()->back()->with("warning","什么都没做");
   }
   public function advancedthreadform(Thread $thread)
   {
      $channels = Channel::all();
      $channels->load('labels');
      return view('admin.advanced_thread_form', compact('thread','channels'));
   }
   public function usermanagement(User $user, Request $request)
   {
     $this->validate($request, [
         'reason' => 'required|string',
         'days' => 'required|numeric',
         'hours' => 'required|numeric',
     ]);
     $var = request('controluser');//
     if ($var=="13"){//设置禁言时间
        Administration::create([
          'user_id' => Auth::id(),
          'operation' => '13',//:增加禁言时间
          'item_id' => $user->id,
          'reason' => request('reason'),
        ]);
        $user->no_posting = Carbon::now()->addDays(request('days'))->addHours(request('hours'));
        $user->save();
        return redirect()->back()->with("success","已经成功处理该用户");
     }
     if ($var=="14"){//解除禁言
        Administration::create([
          'user_id' => Auth::id(),
          'operation' => '14',//:增加禁言时间
          'item_id' => $user->id,
          'reason' => request('reason'),
        ]);
        $user->no_posting = Carbon::now();
        $user->save();
        return redirect()->back()->with("success","已经成功处理该用户");
     }

     return redirect()->back()->with("warning","什么都没做");
   }

   public function sendpublicmessageform()
   {
     return view('admin.send_publicmessage');
   }
   public function sendpublicmessage(Request $request)
   {
       //公共通知效率太低，取消
      // $this->validate($request, [
      //     'body' => 'required|string|max:20000|min:10',
      //  ]);
      //  $receivers = User::all();
      //  $message_body = DB::table('message_bodies')->insertGetId([
      //       'content' => request('body'),
      //       'group_messaging' => 1,
      //    ]);
      //  foreach($receivers as $receiver){
      //    Message::create([
      //       'message_body' => $message_body,
      //       'poster_id' => Auth::id(),
      //       'receiver_id' => $receiver->id,
      //       'private' => false,
      //    ]);
      //    $receiver->increment('message_reminders');
      //  }
      //  return redirect()->back()->with('success','您已成功发布公共通知');

   }

   public function create_tag_form(){
       $labels_tongren = Label::where('channel_id',2)->get();
       $tags_tongren_yuanzhu = Tag::where('tag_group',10)->get();
       $tags_tongren_cp = Tag::where('tag_group',20)->get();
       return view('admin.create_tag',compact('labels_tongren','tags_tongren_yuanzhu','tags_tongren_cp'));
   }
   public function store_tag(Request $request){
       if($request->tongren_tag_group==='1'){//同人原著tag
           Tag::create([
               'tag_group' => 10,
               'tagname'=>$request->tongren_yuanzhu,
               'tag_explanation'=>$request->tongren_yuanzhu_full,
               'label_id' =>$request->label_id,
           ]);
           return redirect()->back()->with("success","成功创立同人原著tag");
       }
       if($request->tongren_tag_group==='2'){//同人CPtag
           Tag::create([
               'tag_group' => 20,
               'tagname'=>$request->tongren_cp,
               'tag_explanation'=>$request->tongren_cp_full,
               'tag_belongs_to' =>$request->tongren_yuanzhu_tag_id,
           ]);
           return redirect()->back()->with("success","成功创立同人CPtag");
       }
       return redirect()->back()->with("warning","什么都没做");
   }
}
