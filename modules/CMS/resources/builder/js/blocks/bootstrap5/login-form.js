Astero.Blocks.add('bootstrap5/login-form', {
    name: 'Login form',
    image: 'https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/fd3f41be24ffb976d66edf08adc4b2453a871b19.jpeg',
    html: `
<div class="container">
    <div class="row justify-content-md-center">

        <div class="col-sm-6 col-md-6 col-lg-6">
            <div class="account-wall">
                <div id="my-tab-content" class="tab-content">
						<div class="tab-pane active" id="login">
               		    <img class="profile-img img-fluid rounded-circle" src="https://source.unsplash.com/9UVmlIb0wJU/200x200"
                    alt="">
               			<form class="form-signin" action="" method="">
               				<input type="text" class="form-control" placeholder="Username" required autofocus>
               				<input type="password" class="form-control" placeholder="Password" required>
               				<input type="submit" class="btn btn-lg btn-secondary w-100" value="Sign In" />
               			</form>
               			<div id="tabs" data-tabs="tabs">
               				<p class="text-center"><a href="#register" data-bs-toggle="tab">Need an Account?</a></p>
               				<p class="text-center"><a href="#select" data-bs-toggle="tab">Select Account</a></p>
              				</div>
						</div>
						<div class="tab-pane" id="register">
							<form class="form-signin" action="" method="">
								<input type="text" class="form-control" placeholder="User Name ..." required autofocus>
								<input type="email" class="form-control" placeholder="Emaill Address ..." required>
								<input type="password" class="form-control" placeholder="Password ..." required>
								<input type="submit" class="btn btn-lg btn-secondary w-100" value="Sign Up" />
							</form>
							<div id="tabs" data-tabs="tabs">
               			<p class="text-center"><a href="#login" data-bs-toggle="tab">Have an Account?</a></p>
              			</div>
						</div>
						<div class="tab-pane" id="select">
							<div id="tabs" data-tabs="tabs">
								<div class="account-select">
									<a href="#user1" data-bs-toggle="tab" class="d-flex text-decoration-none text-dark">
										<div class="flex-shrink-0">
											<img class="select-img" src="https://source.unsplash.com/9UVmlIb0wJU/500x500"
                    alt="">
										</div>
										<div class="flex-grow-1 ms-3">
											<h4 class="select-name">User Name 1</h4>
										</div>
									</a>
								</div>
                                <hr />
								<div class="account-select">
									<a href="#user2" data-bs-toggle="tab" class="d-flex text-decoration-none text-dark">
										<div class="flex-shrink-0">
											<img class="select-img" src="https://lh5.googleusercontent.com/-b0-k99FZlyE/AAAAAAAAAAI/AAAAAAAAAAA/eu7opA4byxI/photo.jpg?sz=120"
                    alt="">
										</div>
										<div class="flex-grow-1 ms-3">
											<h4 class="select-name">User Name 2</h4>
										</div>
									</a>
								</div>
                                <hr />
               			<p class="text-center"><a href="#login" data-bs-toggle="tab">Back to Login</a></p>
              			</div>
						</div>
						<div class="tab-pane" id="user1">
							<img class="profile-img" src="https://lh5.googleusercontent.com/-b0-k99FZlyE/AAAAAAAAAAI/AAAAAAAAAAA/eu7opA4byxI/photo.jpg?sz=120"
                    alt="">
							<h3 class="text-center">User Name 1</h3>
							<form class="form-signin" action="" method="">
								<input type="hidden" class="form-control" value="User Name">
								<input type="password" class="form-control" placeholder="Password" autofocus required>
								<input type="submit" class="btn btn-lg btn-secondary w-100" value="Sign In" />
							</form>
							<p class="text-center"><a href="#login" data-bs-toggle="tab">Back to Login</a></p>
               		<p class="text-center"><a href="#select" data-bs-toggle="tab">Select another Account</a></p>
						</div>
						<div class="tab-pane" id="user2">
							<img class="profile-img" src="https://lh5.googleusercontent.com/-b0-k99FZlyE/AAAAAAAAAAI/AAAAAAAAAAA/eu7opA4byxI/photo.jpg?sz=120"
                    alt="">
							<h3 class="text-center">User Name 2</h3>
							<form class="form-signin" action="" method="">
								<input type="hidden" class="form-control" value="User Name">
								<input type="password" class="form-control" placeholder="Password" autofocus required>
								<input type="submit" class="btn btn-lg btn-secondary w-100" value="Sign In" />
							</form>
							<p class="text-center"><a href="#login" data-bs-toggle="tab">Back to Login</a></p>
               		<p class="text-center"><a href="#select" data-bs-toggle="tab">Select another Account</a></p>
						</div>
					</div>
            </div>
        </div>
    </div>
<style>
/* body{
    background-color:#f5f5f5;
} */
.form-signin
{
    max-width: 330px;
    padding: 15px;
    margin: 0 auto;
}
.form-signin .form-control
{
    position: relative;
    font-size: 16px;
    height: auto;
    padding: 10px;
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}
.form-signin .form-control:focus
{
    z-index: 2;
}
.form-signin input[type="text"]
{
    margin-bottom: -1px;
    border-bottom-left-radius: 0;
    border-bottom-right-radius: 0;
}
.form-signin input[type="password"]
{
    margin-bottom: 10px;
    border-top-left-radius: 0;
    border-top-right-radius: 0;
}
.account-wall
{
    margin-top: 80px;
    padding: 40px 0px 20px 0px;
    background-color: #ffffff;
    box-shadow: 0 2px 10px 0 rgba(0, 0, 0, 0.16);
}
.login-title
{
    color: #555;
    font-size: 22px;
    font-weight: 400;
    display: block;
}
.profile-img
{
    width: 96px;
    height: 96px;
    margin: 0 auto 10px;
    display: block;
    -moz-border-radius: 50%;
    -webkit-border-radius: 50%;
    border-radius: 50%;
}
.select-img
{
	border-radius: 50%;
    display: block;
    height: 75px;
    margin: 0 30px 10px;
    width: 75px;
    -moz-border-radius: 50%;
    -webkit-border-radius: 50%;
    border-radius: 50%;
}
.select-name
{
    display: block;
    margin: 30px 10px 10px;
}

.logo-img
{
    width: 96px;
    height: 96px;
    margin: 0 auto 10px;
    display: block;
    -moz-border-radius: 50%;
    -webkit-border-radius: 50%;
    border-radius: 50%;
}
</style>
</div>
`,
});
