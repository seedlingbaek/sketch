@extends('layouts.default')
@section('title', Helper::convert_to_title($thread->title))

@section('content')
<div class="container-fluid">
   <div class="col-sm-10 col-sm-offset-1 col-md-8 col-md-offset-2">
      @include('shared.errors')
       <!-- 首页／版块／类型 -->
       @include('threads._site_map')
      <div class="panel panel-default">
          <div class="panel-body">
            <!-- 主题介绍部分 -->
            @if($thread->book_id>0)
              @include('books._book_profile')
              <div><a href="{{ route('book.show', $thread->book_id) }}">文库阅读模式</a></div>
            @else
              @include('threads._thread_profile')
            @endif
         </div>
      </div>
      <!-- 回帖主体 -->
      <div class="panel panel-default id = "post{{ $post->id }}">
         <div class="panel-heading">
            <div class="row">
               <div class="col-xs-12">
                  @include('posts._post_profile')
               </div>
            </div>
         </div>
         <div class="panel-body post-body">
            @include('posts._post_body')
         </div>

         @if(Auth::check())
            <div class="text-right post-vote">
               @include('posts._post_vote')
            </div>
         @endif
         <div class="panel-footer">
            @foreach($postcomments as $comment_no=>$postcomment)
                  @include('posts._post_comment')
            @endforeach
            {{ $postcomments->links() }}
         </div>
      </div>
      @if(auth()->check())
         @include('threads._reply')
      @endif
   </div>
</div>
@stop
