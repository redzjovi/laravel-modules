@if ($frontendPagesHomeProductsTestimonialsCarousel)
    <div class="carousel slide" data-ride="carousel" id="frontend_pages_home_product_testimonials_carousel">
        <!-- Indicators -->
        <ol class="carousel-indicators">
            @foreach ($frontendPagesHomeProductsTestimonialsCarousel as $i => $slide)
                <li class="{{ $i == 0 ? 'active' : '' }}" data-slide-to="{{ $i }}" data-target="#frontend_pages_home_product_testimonials_carousel"></li>
            @endforeach
        </ol>

        <!-- Wrapper for slides -->
        <div class="carousel-inner" role="listbox">
            @foreach ($frontendPagesHomeProductsTestimonialsCarousel as $i => $slide)
                <div align="center" class="item {{ $i == 0 ? 'active' : '' }}">
                    <img alt="{{ $slide['title'] }}" src="{{ $slide['image_thumbnail_url'] }}" style="max-height: 300px" />
                    <div class="carousel-caption">
                        <h3>
                            <a href="{{ $slide['url'] }}">{{ $slide['title'] }}</a>
                        </h3>
                        <p>{{ $slide['others']['postTestimonial']->rating }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Controls -->
        <a class="left carousel-control" data-slide="prev" href="#frontend_pages_home_product_testimonials_carousel" role="button">
            <span aria-hidden="true" class="glyphicon glyphicon-chevron-left"></span>
            <span class="sr-only">@lang('cms::cms.previous')</span>
        </a>
        <a class="right carousel-control" data-slide="next" href="#frontend_pages_home_product_testimonials_carousel" role="button">
            <span aria-hidden="true" class="glyphicon glyphicon-chevron-right"></span>
            <span class="sr-only">@lang('cms::cms.next')</span>
        </a>
    </div>
    <hr />
@else
    <input name="frontendPagesHomeProductsTestimonialsCarousel" type="hidden" value="No menu where slug = frontend-home-product-testimonials-featured" />
@endif
