@if ($categories->count() > 0)
    @php
        // At least 4 cols so desktop always fills 4 per row (e.g. 6 items → 4+2, not 3+3)
        $homeCategoryCols = max((int) ceil($categories->count() / 2), 4);
    @endphp
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

                        {{-- 2-row grid: desktop 4/row visible, mobile 2/row, rest overflow-x --}}
                        <div class="home-categories-scroll mt-3"
                             style="--home-cat-cols: {{ $homeCategoryCols }};">
                            @foreach($categories as $category)
                                <div class="home-category-item text-center __cate-item">
                                    <a href="{{ route('category-products', ['slug' => $category['slug']]) }}"
                                       class="d-flex flex-column align-items-center text-decoration-none">
                                        <div class="__img home-category-img">
                                            <img loading="lazy" alt="{{ $category->name }}"
                                                 src="{{ getStorageImages(path: $category->icon_full_url, type: 'category') }}">
                                        </div>
                                        <h3 class="text-center home-category-name mx-auto fs-13 font-semibold mt-2 letter-spacing-0 line--limit-2 mb-0">
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

    <style>
        .home-categories-scroll {
            --home-cat-gap: 12px;
            --home-cat-visible: 4;
            display: grid;
            grid-template-rows: repeat(2, auto);
            grid-template-columns: repeat(
                var(--home-cat-cols),
                calc((100% - ((var(--home-cat-visible) - 1) * var(--home-cat-gap))) / var(--home-cat-visible))
            );
            gap: 16px var(--home-cat-gap);
            overflow-x: auto;
            overflow-y: hidden;
            scroll-snap-type: x proximity;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 6px;
            scrollbar-width: thin;
            scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
        }

        .home-categories-scroll::-webkit-scrollbar {
            height: 4px;
        }

        .home-categories-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .home-categories-scroll::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.18);
            border-radius: 4px;
        }

        .home-category-item {
            scroll-snap-align: start;
            min-width: 0;
        }

        .home-category-item .home-category-img {
            width: 88px;
            height: 88px;
            margin-inline: auto;
            border-radius: 50%;
            overflow: hidden;
            background: #f7f8fa;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
            transition: box-shadow 0.25s ease, transform 0.25s ease;
        }

        .home-category-item .home-category-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            transition: transform 0.35s ease;
        }

        .home-category-item:hover .home-category-img {
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.1);
        }

        .home-category-item:hover .home-category-img img {
            transform: scale(0.92);
        }

        .home-category-name {
            max-width: 100%;
            color: inherit;
            padding-inline: 4px;
        }

        @media (max-width: 767.98px) {
            .home-categories-scroll {
                --home-cat-gap: 10px;
                --home-cat-visible: 2;
                gap: 14px var(--home-cat-gap);
            }

            .home-category-item .home-category-img {
                width: 72px;
                height: 72px;
            }
        }
    </style>
@endif
