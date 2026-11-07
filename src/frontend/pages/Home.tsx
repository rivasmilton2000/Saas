// Contenido y clases conservados desde src/public/index.php; servicios ampliados en React.
import {Link} from 'react-router-dom';
import {WebServices} from './WebServices';
export function Home() { return <>






<section data-stellar-background-ratio="0.3" id="home" className="home_bg">
<div className="container">
<div className="row">
<div className="col-lg-12 col-sm-12 col-xs-12 text-center">
<div className="hero-text">
<h2>{"Somos una compañia que ofrece servicios y facilidades a su negocio"}</h2>

<p>{"Zentra viene a facilitar sus problemas con tecnologia!"}</p>

<a href="/about">{"Saber mas"}</a>
</div>
</div>

</div>

</div>

</section>





<section className="benefits_area section-padding" id="servicios">
<div className="container">
<div className="section-title text-center">
<h2>{"¿Qué ofrecemos?"}</h2>

<p>Soluciones contables para gestionar tu empresa y servicios web para dar a conocer tu negocio.</p>
</div>

<div className="row">
<div className="col-lg-4 col-md-6 col-sm-12">
<div className="single_benefit">
<i className="ti-receipt"></i>

<h4>{"Facturación Electrónica"}</h4>

<p>Organiza tus documentos tributarios electrónicos y consulta tus libros de compras, ventas y retenciones.</p>
</div>
</div>

<div className="col-lg-4 col-md-6 col-sm-12">
<div className="single_benefit">
<i className="ti-shopping-cart"></i>

<h4>{"Gestión Empresarial"}</h4>

<p>Centraliza tu contabilidad, compras y ventas. Trabaja con diario, balance y libros DTE por empresa.</p>
<Link className="btn_one" to="/contact?service=contabilidad">Consultar servicios contables</Link>
</div>
</div>

<div className="col-lg-4 col-md-6 col-sm-12">
<div className="single_benefit">
<i className="ti-package"></i>

<h4>{"Control de Inventario"}</h4>

<p>{"\n                        Gestiona existencias, movimientos y disponibilidad de productos en tiempo real.\n                    "}</p>
</div>
</div>

<div className="col-lg-4 col-md-6 col-sm-12">
<div className="single_benefit">
<i className="ti-user"></i>

<h4>{"Clientes y Proveedores"}</h4>

<p>{"\n                        Administra toda tu información comercial de forma organizada y eficiente.\n                    "}</p>
</div>
</div>

<div className="col-lg-4 col-md-6 col-sm-12">
<div className="single_benefit">
<i className="ti-bar-chart"></i>

<h4>{"Reportes Inteligentes"}</h4>

<p>{"\n                        Obtén información clara y precisa para analizar el desempeño de tu negocio.\n                    "}</p>
</div>
</div>

<div className="col-lg-4 col-md-6 col-sm-12">
<div className="single_benefit">
<i className="ti-cloud"></i>

<h4>{"Plataforma en la Nube"}</h4>

<p>{"\n                        Accede a la información de tu empresa desde cualquier lugar y dispositivo.\n                    "}</p>
</div>
</div>
</div>
<WebServices/>
</div>
</section>





<section className="why_choose_area section-padding">
<div className="container">
<div className="row align-items-center">


<div className="col-lg-5 col-md-12 col-sm-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s">
<div className="single_why_choose">
<h2>{"Gestiona tu empresa desde un solo lugar"}</h2>

<p>{"\n                        Zentra simplifica la administración de tu negocio. Controla ventas, compras,\n                        inventario, clientes, proveedores y documentos tributarios electrónicos\n                        desde una plataforma segura y fácil de utilizar.\n                    "}</p>

<p>{"\n                        Optimiza procesos, reduce errores operativos y toma mejores decisiones\n                        mediante reportes claros y acceso en tiempo real a la información de tu empresa.\n                    "}</p>

<div className="feature-badge-group">
<span className="feature-badge">{"Facturación electrónica"}</span>

<span className="feature-badge">{"Inventario"}</span>

<span className="feature-badge">{"Reportes"}</span>

<span className="feature-badge">{"Clientes"}</span>
</div>

<a className="btn_one" href="/pricing">{"Ver Planes"}</a>
</div>
</div>



<div className="col-lg-7 col-md-12 col-sm-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s">
<div className="single_why_choose_img">
<img src="/public-assets/img/Dashboard.png" className="img-fluid" alt="Dashboard Zentra" />

<img src="/public-assets/img/Dashboard2.png" className="dashboard-back" alt="Dashboard Zentra" />
</div>
</div>
</div>
</div>
</section>





<section className="our_story_area section-padding">
<div className="container">
<div className="row align-items-center">
<div className="col-lg-6 col-md-12 col-sm-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.1s" data-wow-offset="0">
<div className="our_story_img">
<img src="/public-assets/img/Imagen_zentra.png" className="img-fluid" alt="Nuestra historia Zentra" />
</div>
</div>

<div className="col-lg-6 col-md-12 col-sm-12 wow fadeInUp" data-wow-duration="1s" data-wow-delay="0.2s" data-wow-offset="0">
<div className="our_story_content">
<span className="story_label">{"Nuestra historia"}</span>

<h2>{"¿Por qué nació Zentra?"}</h2>

<p>{"\n                        Zentra nace con el propósito de transformar la gestión empresarial\n                        mediante la tecnología, ayudando a empresas, emprendedores y\n                        profesionales a administrar sus operaciones de manera más eficiente,\n                        segura y ordenada.\n                    "}</p>

<p>{"\n                        Nuestra plataforma integra ventas, compras, inventario, clientes,\n                        proveedores y documentos tributarios electrónicos, facilitando el\n                        cumplimiento fiscal y el control de la información desde un solo lugar.\n                    "}</p>

<p>{"\n                        Creemos que la tecnología debe simplificar procesos, reducir tareas\n                        manuales e impulsar mejores decisiones para el crecimiento de cada negocio.\n                    "}</p>

<a className="btn_one" href="/about">{"Conocer más"}</a>
</div>
</div>
</div>
</div>
</section>










</>; }
