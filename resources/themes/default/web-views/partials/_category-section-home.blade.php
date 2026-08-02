@if ($categories->count() > 0)
    <section class="container py-0 rtl px-0 px-md-3">
        <div class="__inline-62">
            <div>
                <div class="card __shadow h-100 max-md-shadow-0">
                    <div class="card-body">
                        {{-- Header --}}
                        <div class="d-flex justify-content-between align-items-baseline">
                            <h2 class="categories-title m-0 letter-spacing-0 h5 fw-bold">
                                <span class="fw-bold">{{ translate('categories') }}</span>
                            </h2>
                            <div>
                                <a class="text-capitalize view-all-text web-text-primary"
                                   href="{{ route('categories') }}">
                                    {{ translate('view_all') }}
                                    <i class="czi-arrow-{{ Session::get('direction') === 'rtl' ? 'left mr-1 ml-n1 mt-1 float-left' : 'right ml-1 mr-n1' }}"></i>
                                </a>
                            </div>
                        </div>

                        {{-- Two‑row horizontal scroll container (Bootstrap + inline styles) --}}
                        <div class="d-flex flex-column flex-wrap overflow-auto mt-3"
                             style="height: 200px; overflow-x: auto; overflow-y: hidden; gap: 10px; align-content: flex-start;">
                            
                            @foreach($categories as $category)
                                <div class="text-center"
                                     style="flex: 0 0 140px; width: 140px; height: 90px; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                    <a href="{{ route('category-products', ['slug' => $category['slug']]) }}"
                                       class="d-flex flex-column align-items-center">
                                        <div class="__img">
                                            <img loading="lazy" alt="{{ $category->name }}"
                                                 src="{{ getStorageImages(path: $category->icon_full_url, type: 'category') }}"
                                                 style="max-width: 60px; max-height: 60px;">
                                        </div>
                                        <h3 class="text-center fs-13 font-semibold mt-1 letter-spacing-0 line--limit-2"
                                            style="font-size: 13px; margin: 0; max-width: 120px;">
                                            {{ Str::limit($category->name, 15) }}
                                        </h3>
                                    </a>
                                </div>
                            @endforeach
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
@endif