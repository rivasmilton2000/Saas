import {Controller,ForbiddenException,Get,Inject,Module,Query,Req,UseGuards} from '@nestjs/common';
import {MysqlService} from '../../conexion/conexiones';
import {AuthGuard,AuthRequest} from './auth';
import {AccountingModule,AccountingService} from './accounting';
import {AdminModule,AdminService} from './admin';
import {ok} from './common';

@Controller('api/dashboard')
@UseGuards(AuthGuard)
class DashboardController{
  constructor(@Inject(MysqlService) private readonly db:MysqlService,@Inject(AccountingService) private readonly accounting:AccountingService,@Inject(AdminService) private readonly admin:AdminService){}
  @Get() async summary(@Req() req:AuthRequest,@Query() query:Record<string,unknown>){
    if(req.user.rol==='admin')return ok(await this.admin.overview(query));
    if(!req.user.id_plan)throw new ForbiddenException('Selecciona tu membresia para continuar.');
    let companyId=query.company_id||query.id_empresa;
    if(!companyId){const [company]=await this.db.rows<{id:number}>('SELECT id FROM empresas WHERE id_usuario=? AND estado=1 ORDER BY ultima_vez_usada DESC,id LIMIT 1',[req.user.id]);companyId=company?.id;}
    if(!companyId)return ok({empresa:null});
    return ok(await this.accounting.overview(req.user.id,{...query,company_id:companyId}));
  }
}
@Module({imports:[AccountingModule,AdminModule],controllers:[DashboardController]})
export class DashboardModule{}
