<!DOCTYPE html>
<html lang="en">

	<!-- START HEAD -->
	<?php include 'partials/head.php'; ?>
	<!-- END HEAD -->
	
    <body data-spy="scroll" data-offset="80">

		<!-- START PRELOADER -->
		<div class="preloader">
			<div class="spinner">
				<div class="double-bounce1"></div>
				<div class="double-bounce2"></div>
			</div>
		</div>
		<!-- END PRELOADER -->		

		<!-- START NAVBAR -->
        <?php include 'partials/navbar.php'; ?>
        <!-- END NAVBAR-->								
		
		<!-- START SECTION TOP -->
		<section class="section-top" style="background-image: url(assets/img/bg/section-top.png);background-size:cover; background-position: center center;">
			<div class="container">
				<div class="row">
				  <div class="col-lg-12 col-sm-12 col-xs-12 text-center">
					<div class="section-top-title">
						<h1>Our Projects</h1>		
					</div>
				  </div><!--- END COL -->				  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END SECTION TOP -->
		<section class="pricing_area section-padding">
  <div class="container">
    
    <div class="row">
      <div class="col-lg-12 text-center">
        <div class="section-title">
          <h2>Flexible Pricing</h2>
          <p>Simple plans. No hidden fees.</p>
        </div>
      </div>
    </div>

    <div class="row justify-content-center">

      <!-- BASIC -->
      <div class="col-lg-4 col-md-6">
        <div class="pricing_card">
          <h4>Basic</h4>
          <h2>$9<span>/mo</span></h2>

          <ul>
            <li><i class="fa fa-check"></i> 1 Usuario</li>
            <li><i class="fa fa-check"></i> Acceso básico</li>
            <li><i class="fa fa-check"></i> Soporte limitado</li>
            <li class="disabled"><i class="fa fa-times"></i> Reportes</li>
          </ul>

          <a href="#" class="btn_pricing">Start Now</a>
        </div>
      </div>

      <!-- PRO (DESTACADO) -->
      <div class="col-lg-4 col-md-6">
        <div class="pricing_card featured">
          <span class="badge_plan">POPULAR</span>

          <h4>Pro</h4>
          <h2>$19<span>/mo</span></h2>

          <ul>
            <li><i class="fa fa-check"></i> 5 Usuarios</li>
            <li><i class="fa fa-check"></i> Todo lo básico</li>
            <li><i class="fa fa-check"></i> Soporte prioritario</li>
            <li><i class="fa fa-check"></i> Reportes</li>
          </ul>

          <a href="#" class="btn_pricing active_btn">Get Pro</a>
        </div>
      </div>

      <!-- PREMIUM -->
      <div class="col-lg-4 col-md-6">
        <div class="pricing_card">
          <h4>Premium</h4>
          <h2>$39<span>/mo</span></h2>

          <ul>
            <li><i class="fa fa-check"></i> Usuarios ilimitados</li>
            <li><i class="fa fa-check"></i> Todo lo Pro</li>
            <li><i class="fa fa-check"></i> Soporte 24/7</li>
            <li><i class="fa fa-check"></i> Reportes avanzados</li>
          </ul>

          <a href="#" class="btn_pricing">Go Premium</a>
        </div>
      </div>

    </div>
  </div>
</section>
		<!-- START PORTFOLIO PROJECT -->
		<section class="portfolio_project_area section-padding">
			<div class="container">
				<div class="row">
				  <div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="single_project">
						<img src="assets/img/portfolio/1.jpg" class="img-fluid" alt="portfolio" />
						<h1>01</h1>
						<h2>Website Design Agency</h2>
						<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry standard dummy text ever since the when an unknown printer took a galley of type and scrambled it to make a type specimen book. It is a long established fact that a reader.</p>
						<a class="btn_one" href="single_project.html">View Project</a>
					</div>
				  </div><!--- END COL -->
				  <div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="single_project">
						<img src="assets/img/portfolio/2.jpg" class="img-fluid" alt="portfolio" />
						<h1>02</h1>
						<h2>Product Marketing</h2>
						<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry standard dummy text ever since the when an unknown printer took a galley of type and scrambled it to make a type specimen book. It is a long established fact that a reader.</p>
						<a class="btn_one" href="single_project.html">View Project</a>
					</div>
				  </div><!--- END COL -->
				  <div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="single_project">
						<img src="assets/img/portfolio/3.jpg" class="img-fluid" alt="portfolio" />
						<h1>03</h1>
						<h2>App Development</h2>
						<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry standard dummy text ever since the when an unknown printer took a galley of type and scrambled it to make a type specimen book. It is a long established fact that a reader.</p>
						<a class="btn_one" href="single_project.html">View Project</a>
					</div>
				  </div><!--- END COL -->
				  <div class="col-lg-12 col-sm-12 col-xs-12">
					<div class="single_project">
						<img src="assets/img/portfolio/4.jpg" class="img-fluid" alt="portfolio" />
						<h1>04</h1>
						<h2>Business Strategy</h2>
						<p>Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry standard dummy text ever since the when an unknown printer took a galley of type and scrambled it to make a type specimen book. It is a long established fact that a reader.</p>
						<a class="btn_one" href="single_project.html">View Project</a>
					</div>
				  </div><!--- END COL -->				  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END PORTFOLIO PROJECT -->
		
		<!-- START FOOTER -->
		<?php include 'partials/footer.php'; ?>
		<!-- END FOOTER -->	

		<!-- START SCRIPTS -->
		<?php include 'partials/scripts.php'; ?>
		<!-- END SCRIPTS -->		
    </body>
</html>