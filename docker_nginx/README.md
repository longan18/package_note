## Cấu trúc thư mục

````sh
- project
  - docker
  - docker-compose.yml
````
### Cấu hình
````sh
Cấu hình environment trong phần php và db ở file docker-compose phải đồng bộ
````

### Thay đổi `[project]` thành tên project của bạn
````sh
docker\nginx\default.conf - line 6
docker\php\Dockerfile     - line 28, 30
docker-compose.yml        - line 10, 16
````