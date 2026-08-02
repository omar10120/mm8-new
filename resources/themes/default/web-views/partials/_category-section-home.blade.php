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

                        {{-- Unified grid: 4 columns per row → 2 rows for 8 categories --}}
                        <div class="row mt-3">
                            @foreach($categories->take(8) as $category)
                                <div class="col-3 text-center __cate-item">
                                    <a href="{{ route('category-products', ['slug' => $category['slug']]) }}"
                                       class="d-flex flex-column align-items-center">
                                        <div class="__img">
                                            <img loading="lazy" alt="{{ $category->name }}"
                                                 src="{{ getStorageImages(path: $category->icon_full_url, type: 'category') }}">
                                        </div>
                                        <h3 class="text-center max-w-100px mx-auto fs-13 font-semibold mt-2 letter-spacing-0 line--limit-2">
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