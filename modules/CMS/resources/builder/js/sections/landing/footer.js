Astero.Sections.add('footer/footer-1', {
    name: 'Footer 1',
    image:
        Astero.builderAssetsUrl +
        '/screenshots/sections/footer/footer-1-thumb.jpeg',
    html: `<footer class="footer-1" title="footer-1">
  <div class="container" data-v-component-menu="footer" data-v-slug="main-footer">

    <div class="row" data-v-menu-items>

      <div class="col-md">

        <div data-v-component-site>
          <img src="${Astero.builderAssetsUrl}/images/logo-white.png" alt="Site logo dark" loading="lazy" class="logo-default-dark" data-v-site-logo-dark>
          <img src="${Astero.builderAssetsUrl}/images/logo.png" alt="Site logo" loading="lazy" class="logo-default" data-v-site-logo>
        </div>

      </div>


      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>Astero</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Themes</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Plugins</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Blog</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Shop</span>
            </a>
          </div>
        </nav>
      </div>

      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>Resources</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>User documentation</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Developer documentation</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Pricing</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Services</span>
            </a>
          </div>
        </nav>
      </div>

      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>Contact</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#">Contact us</a>
            <a href="#">Portfolio</a>
            <a href="#">About us</a>
            <a href="#">Return form</a>
          </div>
        </nav>
      </div>

      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>My account</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#">Order tracking</a>
            <a href="#">Wishlist</a>
            <a href="#">Orders</a>
            <a href="#">Compare</a>
          </div>
        </nav>
      </div>



    </div>
    <!--
		<div class="row justify-content-end">
			<div class="col-md-3 text-muted text-small mt-5">
				&copy; <span data-v-year>2025</span> <span data-v-sitename>Astero</span>. Powered by <a href="https://astero.com" target="_blank">Astero</a>
			</div>
		</div>
		-->

  </div>

  <div class="footer-copyright">
    <div class="container">
      <div class="d-flex flex-column flex-md-row">
        <div class="text-muted flex-grow-1">
          <a class="btn-link text-muted" href="#">Terms and conditions</a> |
          <a class="btn-link text-muted" href="#">Privacy Policy</a>
        </div>
        <div class="text-muted">
          &copy; <span data-v-year>2025</span>
          <span data-v-global-site.title>Astero</span>. <span>Powered by</span>
          <a href="https://astero.com" class="btn-link text-muted" target="_blank">Astero</a>
        </div>
      </div>
    </div>
  </div>

</footer>`,
});
Astero.Sections.add('footer/footer-2', {
    name: 'Footer 2',
    image:
        Astero.builderAssetsUrl +
        '/screenshots/sections/footer/footer-2-thumb.jpeg',
    html: `<footer class="bg-white" title="footer-2">

  <div class="container py-5">
    <div class="row py-4">
      <div class="col-md">
        <img src="${Astero.builderAssetsUrl}/images/logo.png" alt="Site logo" loading="lazy" width="180" class="mb-3">
        <p class="font-italic text-muted">Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt.</p>
        <ul class="list-inline mt-4">
          <li class="list-inline-item">
            <a href="#" target="_blank" title="twitter">
              <i class="la la-twitter"></i>
            </a>
          </li>
          <li class="list-inline-item">
            <a href="#" target="_blank" title="facebook">
              <i class="la la-facebook"></i>
            </a>
          </li>
          <li class="list-inline-item">
            <a href="#" target="_blank" title="instagram">
              <i class="la la-instagram"></i>
            </a>
          </li>
          <li class="list-inline-item">
            <a href="#" target="_blank" title="pinterest">
              <i class="la la-pinterest"></i>
            </a>
          </li>
          <li class="list-inline-item">
            <a href="#" target="_blank" title="vimeo">
              <i class="la la-vimeo"></i>
            </a>
          </li>
        </ul>
      </div>
      <div class="col-md">
        <h6 class="text-uppercase font-weight-bold mb-4">Shop</h6>
        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <a href="#" class="text-muted">For Women</a>
          </li>
          <li class="mb-2">
            <a href="#" class="text-muted">For Men</a>
          </li>
          <li class="mb-2">
            <a href="#" class="text-muted">Stores</a>
          </li>
          <li class="mb-2">
            <a href="#" class="text-muted">Our Blog</a>
          </li>
        </ul>
      </div>
      <div class="col-md">
        <h6 class="text-uppercase font-weight-bold mb-4">Company</h6>
        <ul class="list-unstyled mb-0">
          <li class="mb-2">
            <a href="#" class="text-muted">Login</a>
          </li>
          <li class="mb-2">
            <a href="#" class="text-muted">Register</a>
          </li>
          <li class="mb-2">
            <a href="#" class="text-muted">Wishlist</a>
          </li>
          <li class="mb-2">
            <a href="#" class="text-muted">Our Products</a>
          </li>
        </ul>
      </div>
      <div class="col-lg-4 col-md-6 mb-lg-0">
        <h6 class="text-uppercase font-weight-bold mb-4">Newsletter</h6>
        <p class="text-muted mb-4">Lorem ipsum dolor sit amet, consectetur adipisicing elit. At itaque temporibus.</p>
        <div class="p-1 rounded border">
          <div class="input-group">
            <input type="email" placeholder="Enter your email address" aria-describedby="button-addon1" class="form-control border-0 shadow-0">
            <div class="input-group-append">
              <button id="button-addon1" type="submit" class="btn btn-link">
                <i class="la la-paper-plane"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</footer>`,
});
Astero.Sections.add('footer/footer-3', {
    name: 'Footer 3',
    image:
        Astero.builderAssetsUrl +
        '/screenshots/sections/footer/footer-3-thumb.jpeg',
    html: `<footer class="footer-3 bg-dark text-white" title="footer-3">
  <div class="container" data-v-component-menu="footer" data-v-slug="main-menu">

    <div class="row" data-v-menu-items>


      <div class="col-md">

        <div data-v-component-site>
          <img src="${Astero.builderAssetsUrl}/images/logo-white.png" alt="Site logo dark" loading="lazy" class="logo-default-dark" data-v-site-logo-dark>
          <img src="${Astero.builderAssetsUrl}/images/logo.png" alt="Site logo" loading="lazy" class="logo-default" data-v-site-logo>
        </div>

      </div>


      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>Astero</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Themes</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Plugins</span>
            </a>
          </div>
        </nav>
      </div>

      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>Resources</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>User documentation</span>
            </a>
          </div>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#" data-v-menu-item-url>
              <span data-v-menu-item-name>Developer documentation</span>
            </a>
          </div>
        </nav>
      </div>

      <div class="col-md" data-v-menu-item data-v-if="category.children > 0">
        <div class="h6" data-v-menu-item-name>Contact</div>
        <nav data-v-menu-item-recursive>
          <div data-v-menu-item data-v-if="category.children == 0">
            <a href="#">Contact</a>
          </div>
        </nav>
      </div>



    </div>
    <!--
		<div class="row justify-content-end">
			<div class="col-md-3 text-muted text-small mt-5">
				&copy; <span data-v-year>2025</span> <span data-v-sitename>Astero</span>. Powered by <a href="https://astero.com" target="_blank">Astero</a>
			</div>
		</div>
		-->

  </div>

  <div class="footer-copyright">
    <div class="container">
      <div class="d-flex">
        <div class="text-muted text-small flex-grow-1">
          <a class="btn-link text-muted" href="/page/terms-conditions">Terms and conditions</a> |
          <a class="btn-link text-muted" href="/page/privacy-policy">Privacy Policy</a>
        </div>
        <div class="text-muted text-small">
          &copy; <span data-v-year>2025</span>
          <span data-v-sitename>Astero</span>. Powered by <a href="https://astero.com" class="btn-link text-muted" target="_blank">Astero</a>
        </div>
      </div>
    </div>
  </div>

</footer>`,
});
Astero.SectionsGroup['Footer'] = [
    'footer/footer-1',
    'footer/footer-2',
    'footer/footer-3',
];
