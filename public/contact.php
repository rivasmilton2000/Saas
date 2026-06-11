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
						<h1>Contactanos</h1>		
					</div>
				  </div><!--- END COL -->				  
				</div><!--- END ROW -->
			</div><!--- END CONTAINER -->
		</section>
		<!-- END SECTION TOP -->		

		<!-- CONTACT -->
<section id="contact" class="contact_page_area section-padding">
    <div class="container">

        <div class="section-title text-center">
            <h2>Hablemos de tu negocio</h2>
            <p>
                Escríbenos y te ayudaremos a resolver tus dudas sobre Zentra,
                nuestros planes o la implementación de la plataforma.
            </p>
        </div>

        <div class="row contact_info_row">

            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="contact_info_card">
                    <i class="fa fa-envelope"></i>
                    <h4>Correo</h4>
                    <p>contacto@zentra.com</p>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="contact_info_card">
                    <i class="fa fa-phone"></i>
                    <h4>Teléfono</h4>
                    <p>+503 XXXX-XXXX</p>
                </div>
            </div>

            <div class="col-lg-4 col-md-6 col-sm-12">
                <div class="contact_info_card">
                    <i class="fa fa-clock-o"></i>
                    <h4>Horario</h4>
                    <p>Lunes a Viernes<br>8:00 AM - 5:00 PM</p>
                </div>
            </div>

        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9 col-md-12">
                <div class="contact_form_card">
                    <form id="contact-form" method="post">
                        <div class="row">

                            <div class="form-group col-md-6">
                                <input type="text" name="name" class="form-control" placeholder="Nombre completo" required>
                            </div>

                            <div class="form-group col-md-6">
                                <input type="email" name="email" class="form-control" placeholder="Correo electrónico" required>
                            </div>

                            <div class="form-group col-md-12">
                                <input type="text" name="subject" class="form-control" placeholder="Asunto" required>
                            </div>

                            <div class="form-group col-md-12">
                                <textarea rows="6" name="message" class="form-control" placeholder="Escribe tu mensaje..." required></textarea>
                            </div>

                            <div class="col-md-12 text-center">
                                <button type="submit" name="submit" class="contact_btn">
                                    Enviar Mensaje
                                </button>
                            </div>

                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</section>
<!-- END CONTACT -->		
		
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