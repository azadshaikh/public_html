Astero.Blocks.add('bootstrap5/user-online', {
    name: 'User online',
    image: 'https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/75091e3b5e6efba238457f05e6f9edd847de1bf8.jpg',
    html: `
   	<div class="container user-online-thumb">
		<div class="d-flex justify-content-center h-100">
			<div class="image_outer_container">
				<div class="green_icon"></div>
				<div class="image_inner_container">
					<img src="https://source.unsplash.com/9UVmlIb0wJU/500x500">
				</div>
			</div>
		</div>
<style>
.container.user-online-thumb{
	height: 100%;
	align-content: center;
}

.user-online-thumb .image_outer_container{
margin-top: auto;
margin-bottom: auto;
border-radius: 50%;
position: relative;
}

.user-online-thumb .image_inner_container{
border-radius: 50%;
padding: 5px;
background: #833ab4;
background: -webkit-linear-gradient(to bottom, #fcb045, #fd1d1d, #833ab4);
background: linear-gradient(to bottom, #fcb045, #fd1d1d, #833ab4);
}

.user-online-thumb .image_inner_container img{
height: 200px;
width: 200px;
border-radius: 50%;
border: 5px solid white;
}

.user-online-thumb .image_outer_container .green_icon{
 background-color: #4cd137;
 position: absolute;
 right: 30px;
 bottom: 10px;
 height: 30px;
 width: 30px;
 border:5px solid white;
 border-radius: 50%;
}
</style>
</div>
`,
});
