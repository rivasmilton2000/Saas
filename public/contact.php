<?php
session_start();
require_once __DIR__ . '/../src/includes/contactService.php';
?>
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
						<h1>Let's Talk</h1>		
					</div>
				  </div><!--- END COL -->				  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END SECTION TOP -->
		
		<!-- START ADDRESS -->
		<section class="address_area section-padding">
			<div class="container">
				<div class="row">
				  <div class="col-lg-4 col-sm-4 col-xs-12 text-center">
					<div class="single_address">
						<h4>New York</h4>
						<p class="mr_20">133rd Ave Jamaica, <br /> New York(NY)</p>
						<p><a href="tel:415 256 365">+415 256 365</a></p>
						<p><a href="mailto:">admin@monoline.com</a></p>
					</div>
				  </div><!--- END COL -->
				  <div class="col-lg-4 col-sm-4 col-xs-12 text-center">
					<div class="single_address">
						<h4>Los Angeles</h4>
						<p class="mr_20">E 49th St Los Angeles, <br /> California(CA), 90011</p>
						<p><a href="tel:415 256 365">+415 256 365</a></p>
						<p><a href="mailto:">support@monoline.com</a></p>
					</div>
				  </div><!--- END COL -->
				  <div class="col-lg-4 col-sm-4 col-xs-12 text-center">
					<div class="single_address">
						<h4>San Francisco</h4>
						<p class="mr_20">61 Rudden Ave San <br />Francisco, California</p>
						<p><a href="tel:415 256 365">+415 256 365</a></p>
						<p><a href="mailto:">info@monoline.com</a></p>
					</div>
				  </div><!--- END COL -->				  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END ADDRESS -->
		
		<!-- START MAP -->
		<div class="map">
			<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3023.957183635167!2d-74.00402768559431!3d40.71895904512855!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c2598a1316e7a7%3A0x47bb20eb6074b3f0!2sNew%20Work%20City%20-%20(CLOSED)!5e0!3m2!1sbn!2sbd!4v1600305497356!5m2!1sbn!2sbd" style="border:0;" allowfullscreen="" aria-hidden="false" tabindex="0"></iframe>
		</div>	
		<!-- START MAP -->		

		<!-- CONTACT -->
		<div id="contact" class="contact_area section-padding">
			<div class="container">
				<div class="section-title text-center">
					<h2 class="section-title-white">Get in touch.</h2>
					<p class="section-title-white">It is a long established fact that a reader will be distracted by the readable content of a page when looking at its layout.</p>
				</div>				
				<div class="row">					
					<div class="offset-lg-1 col-lg-10 col-sm-12 col-xs-12 text-center wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="contact">
							<form id="contact-form" method="post" enctype="multipart/form-data">
								<div class="row">
									<div class="form-group col-md-6">
										<input type="text" name="name" class="form-control" placeholder="Name" required="required">
									</div>
									<div class="form-group col-md-6">
										<input type="email" name="email" class="form-control" placeholder="Email" required="required">
									</div>
									<div class="form-group col-md-12">
										<input type="text" name="subject" class="form-control" placeholder="Subject" required="required">
									</div>
									<div class="form-group col-md-12">
										<textarea rows="6" name="message" class="form-control" placeholder="Type your message that on your mind..." required="required"></textarea>
									</div>
									<div class="col-md-12 text-center">
										<button type="submit" value="Send message" name="submit" id="submitButton" class="contact_btn" title="Submit Your Message!">Send Message</button>
									</div>
								</div>
							</form>
						</div>
					</div><!-- END COL  -->					
				</div><!-- END ROW -->				
			</div><!--- END CONTAINER -->
		</div>
		<!-- END CONTACT -->		

		<!-- START PARTNER LOGO -->
		<div class="partner-logo section-padding">
			<div class="container">										
				<div class="row text-center">
					<div class="col-lg-2 col-sm-4 col-xs-12 no-padding wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
						<div class="single_logo single_logo_bm">
							<a href="#"><img src="assets/img/partner/1.png" alt="" class="img-fluid"/></a>
						</div>						
					</div><!--- END COL -->
					<div class="col-lg-2 col-sm-4 col-xs-12 no-padding wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
						<div class="single_logo">
							<a href="#"><img src="assets/img/partner/2.png" alt="" class="img-fluid"/></a>
						</div>						
					</div><!--- END COL -->
					<div class="col-lg-2 col-sm-4 col-xs-12 no-padding wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.3s" data-wow-offset="0">
						<div class="single_logo single_logo_bm">
							<a href="#"><img src="assets/img/partner/3.png" alt="" class="img-fluid"/></a>
						</div>						
					</div><!--- END COL -->
					<div class="col-lg-2 col-sm-4 col-xs-12 no-padding wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.4s" data-wow-offset="0">
						<div class="single_logo">
							<a href="#"><img src="assets/img/partner/4.png" alt="" class="img-fluid"/></a>
						</div>						
					</div><!--- END COL -->
					<div class="col-lg-2 col-sm-4 col-xs-12 no-padding wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.5s" data-wow-offset="0">
						<div class="single_logo">
							<a href="#"><img src="assets/img/partner/5.png" alt="" class="img-fluid"/></a>
						</div>						
					</div><!--- END COL -->
					<div class="col-lg-2 col-sm-4 col-xs-12 no-padding wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.6s" data-wow-offset="0">
						<div class="single_logo">
							<a href="#"><img src="assets/img/partner/6.png" alt="" class="img-fluid"/></a>
						</div>						
					</div><!--- END COL -->
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->	
		</div>
		<!-- END PARTNER LOGO -->
		
		<!-- START FOOTER -->
		<?php include 'partials/footer.php'; ?>
		<!-- END FOOTER -->	

		<!-- START SCRIPTS -->
		<?php include 'partials/scripts.php'; ?>
		<!-- END SCRIPTS -->	
		
		<!-- Alerta para mostrar mensajes al usuario -->
		<?php if(isset($_SESSION['alert'])): ?>
		<!-- Genera la alerta swal fire -->
		<script>
			Swal.fire({
			    icon: '<?= $_SESSION['alert']['icon'] ?>',
			    title: '<?= $_SESSION['alert']['title'] ?>',
			    text: '<?= $_SESSION['alert']['message'] ?>'
			});
		</script>
		<!-- Elimina la alerta después de mostrarla -->
		<?php unset($_SESSION['alert']); ?>
		<?php endif; ?>		
    </body>
</html>