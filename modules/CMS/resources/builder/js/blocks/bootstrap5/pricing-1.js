Astero.Blocks.add('bootstrap5/pricing-1', {
    name: 'Pricing table',
    image: 'https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/e92f797807bb4cd880bc3f161d9f9869854b6991.jpeg',
    html: `
<div id="plans">
  <div class="container">
	<div class="row">

		<!-- item -->
		<div class="col-md-4 text-center mb-4">
			<div class="card border-danger panel-pricing h-100">
				<div class="card-header bg-danger text-white">
					<i class="fa fa-desktop"></i>
					<h3>Plan 1</h3>
				</div>
				<div class="card-body text-center">
					<p class="display-4"><strong>$100 / Month</strong></p>
				</div>
				<ul class="list-group list-group-flush text-center">
					<li class="list-group-item"><i class="fa fa-check"></i> Personal use</li>
					<li class="list-group-item"><i class="fa fa-check"></i> Unlimited projects</li>
					<li class="list-group-item"><i class="fa fa-check"></i> 27/7 support</li>
				</ul>
				<div class="card-footer bg-white border-top-0">
					<a class="btn btn-lg w-100 btn-danger" href="#">BUY NOW!</a>
				</div>
			</div>
		</div>
		<!-- /item -->

		<!-- item -->
		<div class="col-md-4 text-center mb-4">
			<div class="card border-warning panel-pricing h-100">
				<div class="card-header bg-warning text-white">
					<i class="fa fa-desktop"></i>
					<h3>Plan 2</h3>
				</div>
				<div class="card-body text-center">
					<p class="display-4"><strong>$200 / Month</strong></p>
				</div>
				<ul class="list-group list-group-flush text-center">
					<li class="list-group-item"><i class="fa fa-check"></i> Personal use</li>
					<li class="list-group-item"><i class="fa fa-check"></i> Unlimited projects</li>
					<li class="list-group-item"><i class="fa fa-check"></i> 27/7 support</li>
				</ul>
				<div class="card-footer bg-white border-top-0">
					<a class="btn btn-lg w-100 btn-warning" href="#">BUY NOW!</a>
				</div>
			</div>
		</div>
		<!-- /item -->

		<!-- item -->
		<div class="col-md-4 text-center mb-4">
			<div class="card border-success panel-pricing h-100">
				<div class="card-header bg-success text-white">
					<i class="fa fa-desktop"></i>
					<h3>Plan 3</h3>
				</div>
				<div class="card-body text-center">
					<p class="display-4"><strong>$300 / Month</strong></p>
				</div>
				<ul class="list-group list-group-flush text-center">
					<li class="list-group-item"><i class="fa fa-check"></i> Personal use</li>
					<li class="list-group-item"><i class="fa fa-check"></i> Unlimited projects</li>
					<li class="list-group-item"><i class="fa fa-check"></i> 27/7 support</li>
				</ul>
				<div class="card-footer bg-white border-top-0">
					<a class="btn btn-lg w-100 btn-success" href="#">BUY NOW!</a>
				</div>
			</div>
		</div>
		<!-- /item -->

		</div>
	</div>
<style>
/* @import url("http://maxcdn.bootstrapcdn.com/font-awesome/4.2.0/css/font-awesome.min.css"); */

.panel-pricing {
  -moz-transition: all .3s ease;
  -o-transition: all .3s ease;
  -webkit-transition: all .3s ease;
  transition: all .3s ease;
}
.panel-pricing:hover {
  box-shadow: 0px 0px 30px rgba(0, 0, 0, 0.2);
}
.panel-pricing .card-header {
  padding: 20px 10px;
}
.panel-pricing .card-header .fa {
  margin-top: 10px;
  font-size: 58px;
}
.panel-pricing .list-group-item {
  color: #777777;
  border-bottom: 1px solid rgba(250, 250, 250, 0.5);
}
.panel-pricing .list-group-item:last-child {
  border-bottom-right-radius: 0px;
  border-bottom-left-radius: 0px;
}
.panel-pricing .list-group-item:first-child {
  border-top-right-radius: 0px;
  border-top-left-radius: 0px;
}
.panel-pricing .card-body {
  /* background-color: #f0f0f0; */
  /* font-size: 40px; */
  color: #777777;
  padding: 20px;
  margin: 0px;
}
</style>
</div>
`,
});
