import {Link} from 'react-router-dom';
import {contactServices, webServices} from '../../shared/public-services';

export function WebServices() {
  return <div className="row public-web-services" id="servicios-web" aria-label="Servicios web">
    {webServices.map(service => <div className="col-lg-4 col-md-6 col-sm-12" key={service.id}>
      <article className="single_benefit">
        <i className={service.icon} aria-hidden="true"/>
        <h4>{contactServices[service.id]}</h4>
        <p>{service.description}</p>
        <Link className="btn_one" to={`/contact?service=${service.id}`}>{service.action}</Link>
      </article>
    </div>)}
  </div>;
}
