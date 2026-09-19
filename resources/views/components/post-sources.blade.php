@props(['post'])

@if($post->sources->isNotEmpty())
    <div class="post-sources" x-data="{ open: false }"
         @mouseenter="if (window.matchMedia('(hover: hover)').matches) open = true"
         @mouseleave="if (window.matchMedia('(hover: hover)').matches) open = false"
         @focusin="if ($event.target.matches(':focus-visible')) open = true"
         @focusout="if (!$el.contains($event.relatedTarget)) open = false"
         @click.outside="open = false"
         @keydown.escape.stop="open = false; $refs.trigger.focus(); $nextTick(() => open = false)">
        <button x-ref="trigger" type="button" class="post-sources-trigger" aria-controls="post-sources-panel-{{ $post->id }}" :aria-expanded="open.toString()"
                @click="open = window.matchMedia('(hover: hover)').matches ? true : !open">
            <i class="bi bi-journal-bookmark" aria-hidden="true"></i><span>Kaynaklar</span>
        </button>

        <div id="post-sources-panel-{{ $post->id }}" class="post-sources-popover" x-show="open" x-cloak x-transition.opacity.duration.120ms role="region" aria-label="Gönderi kaynakları">
            <div class="post-sources-heading">Kaynaklar</div>
            <ul class="post-sources-list">
                @foreach($post->sources as $source)
                    <li>
                        <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer nofollow external" title="{{ $source->url }}">
                            <span class="post-sources-link-copy"><strong>{{ $source->display_label }}</strong>@if(filled($source->label))<small>{{ $source->domain }}</small>@endif</span>
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
