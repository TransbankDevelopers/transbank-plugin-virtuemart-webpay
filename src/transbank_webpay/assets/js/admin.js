jQuery().ready(function($){
	$("#params_MODO").change(function () {
        var modo = this.value;
        var privateKey = "-----BEGIN RSA PRIVATE KEY-----\n" +
                        "MIIEpQIBAAKCAQEAy9ry5Vigkq8vIeHxit7MMhgeNxr4BQ2K9fVbTXOCpCg/dWZB\n" +
                        "6QAS7iXm1F0q6gPjoAl/NESEr1DSN7oxPMUWwjJqaDBQCV3tyg4BJe0zq+EwfV6V\n" +
                        "UrswOF+uxZcN3m8vrIQ00vOugDajI2mNpDFS0wSL4QHv3Y8wUv3r5XpscV8SK3tr\n" +
                        "2P7GPjJtEmSqDQVG7uKxpf9I9AEzDL+ZQkbEFLZ2QgZI46Epsz0ax0Jszkg+JQR7\n" +
                        "5l0LCU7LJCEhA/XeZrRTzzGxvDuNTf9FGqln56KPS42c0Nu+Xxj1fMm6a1hi7av5\n" +
                        "fQO1wmH/8tI64BPDhjBPq23/36nZaPfu8uceSwIDAQABAoIBAQCMmj1B9nj58x5d\n" +
                        "Dkvc7oKEYqIke9NpGMgpkMsihnMq6h+qx5baMBTecQHeo0wAECKltScpU8D4cpQr\n" +
                        "n55qPb3Ov5sotXnenAxwemxMTmh/OliJf/0aDMXbfBM9cUw9iQ6mvKD6htxqzkSD\n" +
                        "HkB1aRepN8+KqB1bAXHhLAXSRzgYkK8x4AT40Qjep2nKCAsYxGD44L8Rj2VU5Eup\n" +
                        "JYReg9HmNc/BP1zBuCDR8OSlnv7QFKqYG7IObRwh3p+lH2LCd1fItwwIrt7v2yZt\n" +
                        "LCpkhWFwgsHCRn0Gcp1oh65B0J8b5uNW0WEPE5CSNXV9ETRejmTMgeDJrg0aNiVz\n" +
                        "ku13L0FRAoGBAP5fMvY0nXmAk2WNNAePi6L7iMNCzyJuVK17T2Prwxp8fOfixyTg\n" +
                        "d+8aOnE38eRRowYpbTDzL0CTOpTzy2Uv3ZKPO84axnAztesLaaJrdOoZlkRp2bOH\n" +
                        "Fyjf1nNqH5aY4YkTSMgsR8ToAPIkWutE0dwJeFa1bx+iVqlISmlF6b19AoGBAM0o\n" +
                        "+czvHXnS24vyU2+dxW4vlAQZdV1Dez8YWMOsCBVTLW+/1h4JdC0YeVe1YAaj4oQm\n" +
                        "6jR6AhDUdkWMsFmOjuv7RUCZxdy9/BEO7itUIGABG9TfL9IQCVym7o9LVvA5mJNY\n" +
                        "NtwWxUyDCXbn+GskwxdwuDbgXiRwAYLq87h59TVnAoGBAMFbaiie3ClU5DYofKlW\n" +
                        "o0VTwKT0rvE0PufUOQfyWc9TW7sEtm5Atsoo/M29QgFVUP/JE7hG0t9aSwiYvT6A\n" +
                        "gQP8HvDPh5IYrKsrdLPCzQ96PbBpadO+14r5g1EeFT7rnV0OLKAEkNWLqdRmcDow\n" +
                        "iPmitTfsGLlKNGffjLizLTcdAoGAGLo2mX+8gsFqWszDR7GxaR1s7q2O2sXWZf1u\n" +
                        "PW9PrhpPYKezi/1Btmm7vlmLEGHSXHFAS0DlXNfuxWU2oCSxjznC2A1wrrFrXzb4\n" +
                        "d9p06H1ZuGeKIPxz1Gn+WOEQwnV+sUuxmQdZkmY8zssYwvk0Vm/slBio4CVr2Tau\n" +
                        "V27DokMCgYEAvY8j4uqkWGjb4IjSKX1AS2rUKPIpjBlXfTfPFzW2JmjdwTIjfojo\n" +
                        "hPUuYIgQonnMqrbyED5L/8TI/Llcu9+8yjpyl3+u92bQ/tDtxVsaAWnEw6KfZEU0\n" +
                        "Z51Pe+rMzRd1FQvvOs9iS/twerSZou1M3AnxH9LiKfAYBj3QjDvNJaQ=\n" +
                        "-----END RSA PRIVATE KEY-----"
        var certificate = "-----BEGIN CERTIFICATE-----\n" +
                        "MIIDWDCCAkACCQD7E+wzp2LfkTANBgkqhkiG9w0BAQsFADBuMQswCQYDVQQGEwJD\n" +
                        "TDELMAkGA1UECAwCUk0xETAPBgNVBAcMCFNBTlRJQUdPMRIwEAYDVQQKDAlUUkFO\n" +
                        "U0JBTksxFDASBgNVBAsMC09QRVJBQ0lPTkVTMRUwEwYDVQQDDAw1OTcwMjAwMDAy\n" +
                        "NzUwHhcNMTUxMjIxMjA1NzE1WhcNMTkxMjIwMjA1NzE1WjBuMQswCQYDVQQGEwJD\n" +
                        "TDELMAkGA1UECAwCUk0xETAPBgNVBAcMCFNBTlRJQUdPMRIwEAYDVQQKDAlUUkFO\n" +
                        "U0JBTksxFDASBgNVBAsMC09QRVJBQ0lPTkVTMRUwEwYDVQQDDAw1OTcwMjAwMDAy\n" +
                        "NzUwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDL2vLlWKCSry8h4fGK\n" +
                        "3swyGB43GvgFDYr19VtNc4KkKD91ZkHpABLuJebUXSrqA+OgCX80RISvUNI3ujE8\n" +
                        "xRbCMmpoMFAJXe3KDgEl7TOr4TB9XpVSuzA4X67Flw3eby+shDTS866ANqMjaY2k\n" +
                        "MVLTBIvhAe/djzBS/evlemxxXxIre2vY/sY+Mm0SZKoNBUbu4rGl/0j0ATMMv5lC\n" +
                        "RsQUtnZCBkjjoSmzPRrHQmzOSD4lBHvmXQsJTsskISED9d5mtFPPMbG8O41N/0Ua\n" +
                        "qWfnoo9LjZzQ275fGPV8ybprWGLtq/l9A7XCYf/y0jrgE8OGME+rbf/fqdlo9+7y\n" +
                        "5x5LAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAAomt9unYM//xUWDsm+EJ9Pwlf27\n" +
                        "cbVpjn4hj9GLMxlES0SoRz1mjSpF5ZBYL/ltjv37FESAZqEJp4lpCKCNdIzHZd4t\n" +
                        "zcA8LKlxqC/TysFr+izLCB9iUUyZbyl/Q/UVAH1WpMll6RxroV4pKPnILG8GosA7\n" +
                        "p/MMf31xeTm/Lh9fXfaMFSACcsjRNNGHjQ0i+nDrO53noJgiKHmQ2vq3Wr/XXbJx\n" +
                        "QBKXNM9qPioE7xas3v2IyOCYbhoqK6TZNZUoRvuuYBSTPVMjY1uaEXW05c1umeAL\n" +
                        "DdmGFOLreH4BI13wG3/hBI/GF4gZBAw4dp7nnK3gcWQxG7CGh+sKNViaJBw=\n" +
                        "-----END CERTIFICATE-----"
        var tbkCertificate = "-----BEGIN CERTIFICATE-----\nMIIDKTCCAhECBFZl7uIwDQYJKoZIhvcNAQEFBQAwWTELMAkGA1UEBhMCQ0wxDjAMBgNVBAgMBUNo\naWxlMREwDwYDVQQHDAhTYW50aWFnbzEMMAoGA1UECgwDa2R1MQwwCgYDVQQLDANrZHUxCzAJBgNV\nBAMMAjEwMB4XDTE1MTIwNzIwNDEwNloXDTE4MDkwMjIwNDEwNlowWTELMAkGA1UEBhMCQ0wxDjAM\n" +
                            "BgNVBAgMBUNoaWxlMREwDwYDVQQHDAhTYW50aWFnbzEMMAoGA1UECgwDa2R1MQwwCgYDVQQLDANr\nZHUxCzAJBgNVBAMMAjEwMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAizJUWTDC7nfP\n3jmZpWXFdG9oKyBrU0Bdl6fKif9a1GrwevThsU5Dq3wiRfYvomStNjFDYFXOs9pRIxqX2AWDybjA\nX/+bdDTVbM+xXllA9stJY8s7hxAvwwO7IEuOmYDpmLKP7J+4KkNH7yxsKZyLL9trG3iSjV6Y6SO5" +
                            "EEhUsdxoJFAow/h7qizJW0kOaWRcljf7kpqJAL3AadIuqV+hlf+Ts/64aMsfSJJA6xdbdp9ddgVF\noqUl1M8vpmd4glxlSrYmEkbYwdI9uF2d6bAeaneBPJFZr6KQqlbbrVyeJZqmMlEPy0qPco1TIxrd\nEHlXgIFJLyyMRAyjX9i4l70xjwIDAQABMA0GCSqGSIb3DQEBBQUAA4IBAQBn3tUPS6e2USgMrPKp\nsxU4OTfW64+mfD6QrVeBOh81f6aGHa67sMJn8FE/cG6jrUmX/FP1/Cpbpvkm5UUlFKpgaFfHv+Kg" +
                            "CpEvgcRIv/OeIi6Jbuu3NrPdGPwzYkzlOQnmgio5RGb6GSs+OQ0mUWZ9J1+YtdZc+xTga0x7nsCT\n5xNcUXsZKhyjoKhXtxJm3eyB3ysLNyuL/RHy/EyNEWiUhvt1SIePnW+Y4/cjQWYwNqSqMzTSW9TP\n2QR2bX/W2H6ktRcLsgBK9mq7lE36p3q6c9DtZJE+xfA4NGCYWM9hd8pbusnoNO7AFxJZOuuvLZI7\nJvD7YLhPvCYKry7N6x3l\n-----END CERTIFICATE-----"
        if (modo != "INTEGRACION"){
            $("#params_id_comercio").val("");
            $("#params_key_secret").val("");
            $("#params_cert_public").val("");
            $("#params_cert_transbank").val("");
        } else {
            $("#params_id_comercio").val("597020000275");
            $("#params_key_secret").val(privateKey);
            $("#params_cert_public").val(certificate);
            $("#params_cert_transbank").val(tbkCertificate);
        }
    });
});
