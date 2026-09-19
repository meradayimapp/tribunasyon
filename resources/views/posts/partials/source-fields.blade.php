@php
    $existingSources = $post->exists
        ? $post->sources->map(fn ($source) => ['label' => $source->label, 'url' => $source->url])->all()
        : [];
    $sourceRows = old('sources', $existingSources);
    $sourceRows = is_array($sourceRows) ? array_values($sourceRows) : $existingSources;
@endphp

<section class="post-source-editor col-12" x-data="postSourcesEditor(@js($sourceRows), 10)" aria-labelledby="post-sources-heading">
    <input type="hidden" name="sources_editor_present" value="1">
    <h2 id="post-sources-heading" class="post-source-editor-title">KAYNAKLAR</h2>
    <p class="post-source-editor-help">Haberde kullandığınız kaynak bağlantılarını ekleyebilirsiniz.</p>

    <div class="post-source-editor-list">
        <template x-for="(row, index) in rows" :key="row.key">
            <div class="post-source-editor-row">
                <div class="post-source-editor-row-heading">
                    <strong x-text="`Kaynak ${index + 1}`"></strong>
                    <button type="button" class="post-source-remove" @click="remove(index)" :aria-label="`Kaynak ${index + 1} bağlantısını kaldır`">Kaldır</button>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label" :for="`source-label-${row.key}`">Kaynak adı <span class="muted fw-normal">(isteğe bağlı)</span></label>
                        <input class="form-control" type="text" :id="`source-label-${row.key}`" :name="`sources[${index}][label]`" x-model="row.label" maxlength="120" placeholder="Örn. TRT Spor">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" :for="`source-url-${row.key}`">URL</label>
                        <input class="form-control" type="url" inputmode="url" :id="`source-url-${row.key}`" :name="`sources[${index}][url]`" x-model="row.url" maxlength="2048" placeholder="https://ornek.com/haber" autocomplete="url">
                    </div>
                </div>
            </div>
        </template>
    </div>

    @if($errors->has('sources') || $errors->has('sources.*.url') || $errors->has('sources.*.label') || $errors->has('sources.*'))
        <div class="text-danger small mt-2" role="alert">{{ $errors->first('sources') ?: $errors->first('sources.*.url') ?: $errors->first('sources.*.label') ?: $errors->first('sources.*') }}</div>
    @endif

    <button type="button" class="post-source-add" @click="add()" :disabled="rows.length >= maximum">
        <span aria-hidden="true">+</span> Kaynak ekle
    </button>
</section>
