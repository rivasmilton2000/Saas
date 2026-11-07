import {Body,Controller,Inject,Module,Post,Req,UnauthorizedException,UnprocessableEntityException,UploadedFile,UseGuards,UseInterceptors} from '@nestjs/common';
import {FileInterceptor} from '@nestjs/platform-express';
import {compare,hash} from 'bcryptjs';
import {mkdir,writeFile,unlink} from 'node:fs/promises';
import {resolve} from 'node:path';
import {randomUUID} from 'node:crypto';
import {z} from 'zod';
import {MysqlService} from '../../conexion/conexiones';
import {AuthGuard,AuthRequest,AuthService} from './auth';
import {AuditService,ok,validate} from './common';

@Controller('api/account')
@UseGuards(AuthGuard)
export class AccountController {
  constructor(@Inject(MysqlService) private readonly db:MysqlService,@Inject(AuthService) private readonly auth:AuthService,@Inject(AuditService) private readonly audit:AuditService){}
  @Post() @UseInterceptors(FileInterceptor('profile_photo',{limits:{fileSize:2*1024*1024,files:1}}))
  async update(@Req() req:AuthRequest,@Body() body:unknown,@UploadedFile() photo?:Express.Multer.File) {
    const data=validate(z.object({username:z.string().trim().regex(/^[A-Za-z0-9._-]{3,50}$/),nombre_completo:z.string().trim().max(150),
      current_password:z.string().default(''),new_password:z.string().default(''),confirm_password:z.string().default(''),remove_photo:z.coerce.number().int().min(0).max(1).default(0)}),body);
    let password:string|null=null,path:string|null=photo?null:req.user.foto_perfil || null,createdPath:string|undefined;
    if(data.current_password || data.new_password || data.confirm_password) {
      if(!req.user.password || !await compare(data.current_password,req.user.password.replace(/^\$2y\$/,'$2b$')))throw new UnauthorizedException('Debes escribir tu clave actual correctamente para cambiarla.');
      if(Buffer.byteLength(data.new_password)<6 || Buffer.byteLength(data.new_password)>72 || data.new_password!==data.confirm_password)throw new UnprocessableEntityException('La nueva clave debe tener entre 6 y 72 bytes y coincidir con la confirmacion.');
      password=await hash(data.new_password,12);
    }
    if(data.remove_photo)path=null;
    if(photo) {
      const b=photo.buffer;
      const extension=b.subarray(0,8).equals(Buffer.from([137,80,78,71,13,10,26,10]))?'png':b[0]===255&&b[1]===216&&b[2]===255?'jpg':/^GIF8[79]a$/.test(b.subarray(0,6).toString())?'gif':b.subarray(0,4).toString()==='RIFF'&&b.subarray(8,12).toString()==='WEBP'?'webp':null;
      if(!extension)throw new UnprocessableEntityException('La foto debe ser JPG, PNG, WEBP o GIF.');
      const name=`${req.user.id}-${randomUUID()}.${extension}`,directory=resolve('src/uploads/profiles');
      await mkdir(directory,{recursive:true});createdPath=resolve(directory,name);await writeFile(createdPath,b,{flag:'wx'});path=`/uploads/profiles/${name}`;
    }
    try {
      await this.db.transaction(async connection=>{
        await this.db.run(`UPDATE usuarios SET username=?,nombre_completo=?,foto_perfil=?${password?',password=?':''} WHERE id=?`,[data.username,data.nombre_completo || null,path,...(password?[password]:[]),req.user.id],connection);
        await this.audit.record(req.user,'actualizar_perfil','Actualizo su perfil.',req,connection);
      });
    }catch(error){if(createdPath)await unlink(createdPath).catch(()=>{});throw error;}
    return ok({user:this.auth.publicUser(await this.auth.byId(req.user.id))},'Perfil actualizado correctamente.');
  }
}
@Module({controllers:[AccountController]})
export class AccountModule {}
