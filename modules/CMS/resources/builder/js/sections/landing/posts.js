Astero.Sections.add('posts/posts-1', {
    name: 'Posts 1',
    image: Astero.builderAssetsUrl + '/screenshots/sections/posts/posts-1-thumb.jpeg',
    html: `<section class="posts-1" title="latest-post-1">
  <div class="container">
    <div class="row">
      <div class="col-12">
        <div class="section-heading text-center">
          <h2>Latest Posts</h2>
        </div>
      </div>
    </div>
  </div>



  <div class="container" data-v-component-posts="posts-1" data-v-limit="3" data-v-image_size="medium">
    <div class="row">



      <div class="col-12 col-lg-4 mb-2" data-v-post>

        <article class="card h-100">
          <div class="card-img-top" data-v-if="post.image">
            <img src="${Astero.builderAssetsUrl}/images/demo/video-1.jpg" alt="" class="w-100" loading="lazy" data-v-size="thumb" data-v-post-image>
          </div>
          <!-- Post Title -->
          <div class="card-body">
            <div class="post-title card-title">
              <a href="#" data-v-post-url>
                <h3 data-v-post-name>
                  Vivamus sed nunc in arcu cursus mollis quis et orci. Interdum et malesuada
                </h3>
              </a>
            </div>
            <!-- Hover Content -->
            <p class="card-text text-muted" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
            <a href="#" title="Read more" role="button" data-v-post-url>
              <span>Read more</span>
              <i class="la la-angle-right"></i>
            </a>
          </div>
        </article>


      </div>



      <div class="col-12 col-lg-4 mb-2" data-v-post>

        <article class="card h-100">
          <div class="card-img-top" data-v-if="post.image">
            <img src="${Astero.builderAssetsUrl}/images/demo/video-1.jpg" alt="" class="w-100" loading="lazy" data-v-size="thumb" data-v-post-image>
          </div>
          <!-- Post Title -->
          <div class="card-body">
            <div class="post-title card-title">
              <a href="#" data-v-post-url>
                <h3 data-v-post-name>
                  Vivamus sed nunc in arcu cursus mollis quis et orci. Interdum et malesuada
                </h3>
              </a>
            </div>
            <!-- Hover Content -->
            <p class="card-text text-muted" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
            <a href="#" title="Read more" role="button" data-v-post-url>
              <span>Read more</span>
              <i class="la la-angle-right"></i>
            </a>
          </div>
        </article>


      </div>



      <div class="col-12 col-lg-4 mb-2" data-v-post>

        <article class="card h-100">
          <div class="card-img-top" data-v-if="post.image">
            <img src="${Astero.builderAssetsUrl}/images/demo/video-1.jpg" alt="" class="w-100" loading="lazy" data-v-size="thumb" data-v-post-image>
          </div>
          <!-- Post Title -->
          <div class="card-body">
            <div class="post-title card-title">
              <a href="#" data-v-post-url>
                <h3 data-v-post-name>
                  Vivamus sed nunc in arcu cursus mollis quis et orci. Interdum et malesuada
                </h3>
              </a>
            </div>
            <!-- Hover Content -->
            <p class="card-text text-muted" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
            <a href="#" title="Read more" role="button" data-v-post-url>
              <span>Read more</span>
              <i class="la la-angle-right"></i>
            </a>
          </div>
        </article>


      </div>



    </div>
  </div>
</section>`,
});
Astero.Sections.add('posts/posts-2', {
    name: 'Posts 2',
    image: Astero.builderAssetsUrl + '/screenshots/sections/posts/posts-2-thumb.jpeg',
    html: `<section class="pt-5 pb-5" title="posts-2">
  <div class="container">
    <div class="row">
      <div class="col-6">
        <h3 class="mb-3">News sections</h3>
      </div>
      <div class="col-6 text-end">
        <a class="btn btn-primary mb-3 me-1" href="#carouselPosts2" role="button" data-bs-slide="prev">
          <i class="la la-arrow-left"></i>
        </a>
        <a class="btn btn-primary mb-3 " href="#carouselPosts2" role="button" data-bs-slide="next">
          <i class="la la-arrow-right"></i>
        </a>
      </div>
      <div class="col-12">
        <div id="carouselPosts2" class="carousel slide" data-bs-ride="carousel">

          <div class="carousel-inner">
            <div class="carousel-item active" data-v-component-posts="slide1" data-v-limit="3" data-v-page="1" data-v-image_size="medium">
              <div class="row">

                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="day67-dog" src="${Astero.builderAssetsUrl}/images/illustrations.co/day67-dog.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>

                  </div>
                </div>
                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="day22-owl" src="${Astero.builderAssetsUrl}/images/illustrations.co/day22-owl.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>
                  </div>
                </div>
                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="day68-happy-cat" src="${Astero.builderAssetsUrl}/images/illustrations.co/day68-happy-cat.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>
                  </div>
                </div>

              </div>
            </div>
            <div class="carousel-item" data-v-component-posts="slide2" data-v-limit="3" data-v-page="2">
              <div class="row">

                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="day79-coffee" src="${Astero.builderAssetsUrl}/images/illustrations.co/day79-coffee.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>

                  </div>
                </div>
                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="109-map-location" src="${Astero.builderAssetsUrl}/images/illustrations.co/109-map-location.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>
                  </div>
                </div>
                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="107-healthy" src="${Astero.builderAssetsUrl}/images/illustrations.co/107-healthy.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>
                  </div>
                </div>

              </div>
            </div>
            <div class="carousel-item" data-v-component-posts="slide3" data-v-limit="3" data-v-page="3">
              <div class="row">

                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="126-namaste-no-hand-shake" src="${Astero.builderAssetsUrl}/images/illustrations.co/126-namaste-no-hand-shake.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>

                  </div>
                </div>
                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="104-dumbbell" src="${Astero.builderAssetsUrl}/images/illustrations.co/104-dumbbell.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>

                    </div>
                  </div>
                </div>
                <div class="col-md-4 mb-3" data-v-post>
                  <div class="card">
                    <img class="img-fluid" loading="lazy" alt="day50-pirahna" src="${Astero.builderAssetsUrl}/images/illustrations.co/day50-pirahna.svg" data-v-post-image>
                    <div class="card-body">
                      <a href="#" data-v-post-url>
                        <h4 class="card-title" data-v-post-name>Interdum et malesuada</h4>
                      </a>
                      <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>`,
});
Astero.Sections.add('posts/posts-3', {
    name: 'Posts 3',
    image: Astero.builderAssetsUrl + '/screenshots/sections/posts/posts-3-thumb.jpeg',
    html: `<section class="posts-3 py-5" title="posts-3">
  <div class="container">

    <div class="row justify-content-center">

      <div class="col-md-8 text-center">
        <h3 class="mb-3">From Our Blog</h3>
        <h6 class="lead">Vivamus sed nunc in arcu cursus mollis quis et orci. Interdum et malesuada.</h6>
      </div>

    </div>

    <div class="row mt-4" data-v-component-posts="posts-3" data-v-limit="3" data-v-page="1" data-v-image_size="medium">

      <div class="col-md-4" data-v-post>
        <div class="card position-relative shadow border-0 mb-4" data-bs-theme="dark">
          <img class="card-img" src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" alt="product" data-v-post-image>
          <div class="card-img-overlay overflow-hidden">
            <div class="d-flex align-items-center">
              <span class="badge bg-primary text-white px-3 py-1 font-weight-normal">New</span>
              <div class="ms-2">
                <span class="ms-2 small">Jan 21, 2024</span>
              </div>
            </div>
            <a href="#" class="text-body" data-v-post-url>
              <h5 class="card-title my-3 font-weight-normal" data-v-post-name>Interdum et malesuada</h5>
            </a>
            <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
          </div>
        </div>
      </div>


      <div class="col-md-4" data-v-post>
        <div class="card position-relative shadow border-0 mb-4" data-bs-theme="dark">
          <img class="card-img" src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" alt="product" data-v-post-image>
          <div class="card-img-overlay overflow-hidden">
            <div class="d-flex align-items-center">
              <span class="badge bg-primary text-white px-3 py-1 font-weight-normal">New</span>
              <div class="ms-2">
                <span class="ms-2 small">Jan 21, 2024</span>
              </div>
            </div>
            <a href="#" class="text-body" data-v-post-url>
              <h5 class="card-title my-3 font-weight-normal" data-v-post-name>Interdum et malesuada</h5>
            </a>
            <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
          </div>
        </div>
      </div>


      <div class="col-md-4" data-v-post>
        <div class="card position-relative shadow border-0 mb-4" data-bs-theme="dark">
          <img class="card-img" src="${Astero.builderAssetsUrl}/images/demo/product.jpg" loading="lazy" alt="product" data-v-post-image>
          <div class="card-img-overlay overflow-hidden">
            <div class="d-flex align-items-center">
              <span class="badge bg-primary text-white px-3 py-1 font-weight-normal">New</span>
              <div class="ms-2">
                <span class="ms-2 small">Jan 21, 2024</span>
              </div>
            </div>
            <a href="#" class="text-body" data-v-post-url>
              <h5 class="card-title my-3 font-weight-normal" data-v-post-name>Interdum et malesuada</h5>
            </a>
            <p class="card-text" data-v-post-excerpt>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Fusce enim nulla, mollis eu metus in, sagittis fringilla tortor. Phasellus purus dignissim convallis.</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>`,
});
Astero.SectionsGroup['Posts'] = ['posts/posts-1', 'posts/posts-2', 'posts/posts-3'];
