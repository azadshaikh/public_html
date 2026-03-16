Astero.Blocks.add('bootstrap5/block-quote', {
    name: 'Block quote',
    image: 'https://d2d3qesrx8xj6s.cloudfront.net/img/screenshots/d9f382e143b77d5a630dd79a2a3860611a8a953c.jpg',
    html: `
<div class="container">
    <blockquote class="quote-box">
      <p class="quotation-mark">
        “
      </p>
      <p class="quote-text">
        Don't believe anything that you read on the internet, it may be fake.
      </p>
      <hr>
      <div class="blog-post-actions clearfix">
        <p class="blog-post-bottom float-start">
          Abraham Lincoln
        </p>
        <p class="blog-post-bottom float-end">
          <span class="badge quote-badge">896</span>  ❤
        </p>
      </div>
    </blockquote>
<style>
blockquote{
    border-left:none
}

.quote-badge{
    background-color: rgba(0, 0, 0, 0.2);
}

.quote-box{

    overflow: hidden;
    margin-top: -50px;
    padding-top: -100px;
    border-radius: 17px;
    background-color: #4ADFCC;
    margin-top: 25px;
    color:white;
    width: 325px;
    box-shadow: 2px 2px 2px 2px #E0E0E0;

}

.quotation-mark{

    margin-top: -10px;
    font-weight: bold;
    font-size:100px;
    color:white;
    font-family: "Times New Roman", Georgia, Serif;

}

.quote-text{

    font-size: 19px;
    margin-top: -65px;
}
</style>
</div>
`,
});
