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
						<h1>Planes de Zentra</h1>		
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
          <h2>Planes flexibles</h2>
          <p>Elige el plan que mejor se adapte al tamaño y operación de tu negocio.</p>
        </div>
      </div>
    </div>

    <div class="row justify-content-center">

      <!-- GRATIS -->
<div class="col-lg-4 col-md-6">
    <div class="pricing_card">
        <h4>Gratis</h4>
        <h2>$0<span>/mes</span></h2>

        <ul>
            <li><i class="fa fa-check"></i> 1 Usuario</li>
            <li><i class="fa fa-check"></i> Facturación básica</li>
            <li><i class="fa fa-check"></i> Inventario básico</li>
            <li><i class="fa fa-check"></i> Soporte por correo</li>
        </ul>

        <a href="#" class="btn_pricing">
            Comenzar Gratis
        </a>
    </div>
</div>

<!-- LIGHT -->
<div class="col-lg-4 col-md-6">
    <div class="pricing_card">
        <h4>Light</h4>
        <h2>$6.99<span>/mes</span></h2>

        <ul>
            <li><i class="fa fa-check"></i> Hasta 3 usuarios</li>
            <li><i class="fa fa-check"></i> Facturación electrónica</li>
            <li><i class="fa fa-check"></i> Inventario</li>
            <li><i class="fa fa-check"></i> Soporte estándar</li>
        </ul>

        <a href="#" class="btn_pricing">
            Elegir Plan
        </a>
    </div>
</div>

<!-- PRO -->
<div class="col-lg-4 col-md-6">
    <div class="pricing_card featured">

        <span class="badge_plan">
            RECOMENDADO
        </span>

        <h4>Pro</h4>
        <h2>$17.99<span>/mes</span></h2>

        <ul>
            <li><i class="fa fa-check"></i> Hasta 10 usuarios</li>
            <li><i class="fa fa-check"></i> Todo lo de Light</li>
            <li><i class="fa fa-check"></i> Reportes avanzados</li>
            <li><i class="fa fa-check"></i> Soporte prioritario</li>
        </ul>

        <a href="#" class="btn_pricing active_btn">
            Elegir Pro
        </a>

    </div>
</div>
<!-- ULTRA -->
<div class="col-lg-4 col-md-6">
    <div class="pricing_card">
        <h4>Ultra</h4>
        <h2>$34.99<span>/mes</span></h2>

        <ul>
            <li><i class="fa fa-check"></i> Usuarios ilimitados</li>
            <li><i class="fa fa-check"></i> Todo lo de Pro</li>
            <li><i class="fa fa-check"></i> Reportes ejecutivos</li>
            <li><i class="fa fa-check"></i> Soporte 24/7</li>
        </ul>

        <a href="#" class="btn_pricing">
            Elegir Ultra
        </a>
    </div>
</div>

<!-- ENTERPRISE -->
<div class="col-lg-4 col-md-6">
    <div class="pricing_card">

        <h4>Enterprise</h4>

        <h2>
            Personalizado
        </h2>

        <ul>
            <li><i class="fa fa-check"></i> Solución empresarial</li>
            <li><i class="fa fa-check"></i> Integraciones avanzadas</li>
            <li><i class="fa fa-check"></i> Implementación dedicada</li>
            <li><i class="fa fa-check"></i> Soporte exclusivo</li>
        </ul>

        <a href="contact.php" class="btn_pricing">
            Solicitar Cotización
        </a>

    </div>
</div>

    </div>
  </div>
</section>
<div class="text-center mt-5">
    <h3>¿No sabes qué plan elegir?</h3>
    <p>
        Nuestro equipo puede ayudarte a encontrar la solución ideal para tu negocio.
    </p>
    <a href="contact.php" class="btn_one">
        Contáctanos
    </a>
</div>
		
		<!-- START FOOTER -->
		<?php include 'partials/footer.php'; ?>
		<!-- END FOOTER -->	

		<!-- START SCRIPTS -->
		<?php include 'partials/scripts.php'; ?>
		<!-- END SCRIPTS -->		
    </body>
</html>