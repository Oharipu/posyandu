@pushOnce('style')
    <link href="/css/quill.snow.css" rel="stylesheet">
@endPushOnce

@pushOnce('script')
    <script src="/js/quill.js" defer></script>
    <script src="/js/quill-image-resize.js" defer></script>
@endPushOnce

<div class="mb-5" x-data="{
    content: '',
    availableImages: [],
    selectedImage: null,
    isImageLoading: true,
    endpoint: '{{ $endpoint ?? '' }}',
    csrf: '{{ csrf_token() }}',
    selectLocalImage(quillInstance) {
        const input = document.createElement('input');
        input.setAttribute('type', 'file');
        input.click();

        // Listen upload local image and save to server
        input.onchange = () => {
            const file = input.files[0];

            // file type is only image.
            if (/^image\//.test(file.type)) {
                this.saveToServer(file, quillInstance);
            } else {
                console.warn('You could only upload images.');
            }
        };
    },
    saveToServer(file, quillInstance) {
        const fd = new FormData();
        fd.append('image', file);

        const xhr = new XMLHttpRequest();
        xhr.open('POST', this.endpoint, true);
        xhr.setRequestHeader('X-CSRF-Token', this.csrf);

        xhr.upload.onprogress = function(event) {
            var progress = Math.round(event.loaded / event.total * 100) + '%';
            var progressBar = document.getElementById('quillProgressBar');

            if (event.lengthComputable) {
                progressBar.style = `width: ${parseFloat(progress)}`;

                // Upload finished
                if (event.loaded == event.total) {
                    progressBar.style = 'width: 0%';
                }
            }
        };

        xhr.onload = function() {
            if (this.status >= 200 && this.status < 300) {
                // this is callback data: url
                const data = JSON.parse(this.responseText);
                // console.log(data);

                // push image url to rich editor.
                const range = quillInstance.getSelection();
                quillInstance.insertEmbed(range.index, 'image', `/${data.url}`);
                // puts the cursor at the end of image
                quillInstance.setSelection(range.index + 1, Quill.sources.SILENT);
            }
        };
        xhr.send(fd);
    }
}" x-init="document.addEventListener('DOMContentLoaded', () => {
    quill = new Quill($refs.quillEditor, {
        scrollingContainer: '.ql-scrolling-container',
        modules: {
            toolbar: {
                container: [
                    [{ 'header': [1, 2, 3, false] }, 'bold', 'italic', 'underline', 'strike'],
                    ['link', 'blockquote', 'code-block', 'image'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ 'indent': '-1' }, { 'indent': '+1' }, { 'direction': 'rtl' }, { 'align': [] }],
                    ['clean']
                ],
                handlers: {
                    image: function() {
                        var range = quill.getSelection();
                        var value = prompt('Please enter your image URL');
                        if (value) {
                            quill.insertEmbed(range.index, 'image', value, Quill.sources.USER);
                        }
                    }
                }
            },
            imageResize: {
                displaySize: true
            },
        },
        theme: 'snow',
        placeholder: '{{ $placeholder ?? 'Write something great!' }}'
    });
    quill.on('text-change', function() {
        let html = quill.root.innerHTML;
        if (html === '<p><br></p>') html = ''
        content = html;
    });
    quill.clipboard.addMatcher(Node.ELEMENT_NODE, function(node, delta) {
        var plaintext = node.innerText.replace(/\s+/g, ' ').trim();
        var Delta = Quill.import('delta');
        return new Delta().insert(plaintext);
    });

    // quill editor add image handler
    quill.getModule('toolbar').addHandler('image', () => {
        $dispatch('open-modal', 'add-image')
    });
    content = (quill.root.innerHTML === '<p><br></p>') ?
        '' :
        quill.root.innerHTML;
});" x-cloak>
    @if ($label ?? null)
        <label for="{{ $name }}" class="form-label block mb-1 font-semibold text-gray-700">
            {{ $label }}
            @if ($optional ?? null)
                <span class="text-sm text-gray-500 font-normal">(optional)</span>
            @endif
        </label>
    @endif

    <div class="relative {{ $errors->has($name) ? 'ql-editor-haserror' : '' }}">

        <div class="w-full pl-px pr-px bg-transparent z-20 absolute left-0 right-0" style="top: 38px;">
            <div id="quillProgressBar" class="bg-green-600 text-xs leading-none h-1" style="width: 0%"></div>
        </div>

        <textarea class="hidden" name="{{ $name }}" :value="content"></textarea>
        <div x-ref="quillEditor" x-model="content"
            class="bg-white min-h-full h-auto focus-visible:outline-none focus:outline-cyan-600">
            {!! old($name, $value ?? '') !!}
        </div>

        @error($name)
            <svg class="absolute z-10 text-red-600 fill-current w-5 h-5" style="top: 12px; right: 12px"
                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path
                    d="M11.953,2C6.465,2,2,6.486,2,12s4.486,10,10,10s10-4.486,10-10S17.493,2,11.953,2z M13,17h-2v-2h2V17z M13,13h-2V7h2V13z" />
            </svg>
            <div class="text-red-600 mt-2 text-sm block leading-tight">{{ $message }}</div>
        @enderror
    </div>

    <x-modal name="add-image">
        <div class="p-6" x-on:close-modal.window="isImageLoading=false"
            x-on:open-modal.window="
            fetch('{{ route('get.images') }}', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                }
            }).then(response => response.json()).then(x=> availableImages=x).catch(e => alert(e.message))
            ">
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Add Image') }}
            </h2>

            <div class="mt-6 grid grid-cols-5 gap-2 max-h-96 overflow-y-auto">
                <div class="relative w-full aspect-square border border-dashed border-gray-400 rounded-md">
                    <label for="upload_new_image"
                        class="w-full h-full flex flex-col text-center items-center justify-center cursor-pointer">
                        <x-heroicon-o-plus class="w-8 h-8" />
                        <span>{{ __('Upload Image') }}</span>
                        <input type="file" accept="image/jpg,image/jpeg,image/png" class="hidden" id="upload_new_image" />
                    </label>
                </div>

                {{-- Images from server --}}
                <template x-for="(image, index) in availableImages" :key="index">
                    <div class="w-full h-full">
                        <label x-bind:for="image"
                            class="w-full h-full rounded-md border border-gray-400 aspect-square object-cover block overflow-hidden"
                            :class="selectedImage === image ? 'border-2 border-cyan-500' : 'border-none'">
                            <input type="radio" name="selected_image" x-bind:id="image"
                                x-on:change="selectedImage = image" class="hidden">
                            <img x-bind:src="image.replace('public/', '/storage/')" class="w-full h-full">
                        </label>
                    </div>
                </template>
            </div>
        </div>
    </x-modal>
</div>
