<?php 
   require_once __DIR__ . '/../src/includes/plansService.php';

   $planes = getActivePlansWithFeatures($pdo);
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

    <?php foreach ($planes as $plan): ?>
        <?php 
           $isFeatured = dbBoolValue($plan['destacado'] ?? false);    
           $precio = $plan['precio'];

           if($precio === null)
            {
                $precioTexto = 'Personalizado';
                $periodoTexto = '';
            }else
            {
                $precioTexto = '$' . number_format((float)$precio, 2);
                $periodoTexto = '/' . htmlspecialchars($plan['periodo'] ?? 'mes');
            }

            $buttonText = $precio === null ? 'Solicitar Cotización' : 'Elegir Plan';
            if(strtolower($plan['nombre']) == 'gratis')
                {
                    $buttonText = 'Comenzar Gratis';
                }
            
            $buttonHref = $precio === null ? 'contact.php' : '#';
        ?>

        <div class="col-lg-4 col-md-6">
            <div class="pricing_card <?= $isFeatured ? 'featured' : '' ?>">

                <?php if ($isFeatured): ?>
                  <span class="badge_plan">RECOMENDADO</span>
                <?php endif; ?>

                <h4><?= htmlspecialchars($plan['nombre']) ?></h4>

                <h2>
                   <?= $precioTexto ?>
                   <?php if ($periodoTexto !== ''): ?>
                    <span><?= $periodoTexto ?></span>
                   <?php endif; ?>
                </h2>

                <?php if(!empty($plan['descripcion'])): ?>
                    <p><?= htmlspecialchars($plan['descripcion']) ?></p>
                <?php endif; ?>
                
                <ul>
                    <?php foreach (($plan['caracteristicas'] ?? []) as $caracteristica): ?>
                        <li class="<?= dbBoolValue($caracteristica['incluido'] ?? true) ? '' : 'disabled' ?>">
                            <?php if (dbBoolValue($caracteristica['incluido'] ?? true)): ?>
                                <i class="fa fa-check"></i>
                            <?php else: ?>
                                <i class="fa fa-times"></i>
                            <?php endif; ?> 

                            <?= htmlspecialchars($caracteristica['caracteristica']) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <a href="<?= $buttonHref ?>" class="btn_pricing <?= $isFeatured ? 'active_btn' : '' ?>">
                    <?= $buttonText ?>
                </a>
            </div>
        </div>
    
    <?php endforeach; ?>

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