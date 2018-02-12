<?xml version="1.0" ?>
<rss version="2.0">
  <channel>
    <title>{{ $feed['title'] }}</title>
    <link>{{ $feed['uri'] }}</link>
    <description>{{ $feed['description'] }}</description>
    @foreach ($records as $item)
    <item>
      <title>{{ $item->getTitle() }}</title>
      <link>{!! $item->getLink() !!}</link>
      <description>{{ $item->getDescription() }}</description>
    </item>
    @endforeach  
  </channel>
</rss>
