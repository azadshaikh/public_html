Astero.Blocks.add('bootstrap5/loading-circle', {
    name: 'Loading circle',
    image: 'https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/39f0571b9a377cb7ac9c0c11d2346b07dabe1c66.png',
    html: `
<div class="loading-circle">
  <div class="loader">
    <div class="loader">
        <div class="loader">
           <div class="loader">

           </div>
        </div>
    </div>
  </div>
<style>

.loading-circle {
	width: 150px;
    height: 150px;
}

.loader {
    width: calc(100% - 0px);
	height: calc(100% - 0px);
	border: 8px solid #162534;
	border-top: 8px solid #09f;
	border-radius: 50%;
	animation: rotate 5s linear infinite;
}

@keyframes rotate {
100% {transform: rotate(360deg);}
}
</style>
</div>
`,
});
